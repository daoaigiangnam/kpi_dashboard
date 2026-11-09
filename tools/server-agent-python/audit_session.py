"""Crash-recoverable audit session state machine.

Effects are injected as idempotent desired-state callbacks. This module never
runs a shell command, edits auditd rules, or changes kernel audit state itself.
"""
from __future__ import annotations

import json
import os
import tempfile
import threading
import uuid
from pathlib import Path
from typing import Callable


class AuditSessionConflict(RuntimeError):
    pass


class AuditSessionManager:
    STATES = {"stopped", "starting", "running", "stopping"}

    def __init__(
        self,
        state_path: str | Path,
        ensure_enabled: Callable[[str], None],
        ensure_disabled: Callable[[str], None],
    ):
        self.path = Path(state_path)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.ensure_enabled = ensure_enabled
        self.ensure_disabled = ensure_disabled
        self._lock = threading.RLock()
        if not self.path.exists():
            self._write({"state": "stopped", "session_id": None, "command_id": None})
        self._read()

    def _read(self) -> dict:
        try:
            value = json.loads(self.path.read_text(encoding="utf-8"))
        except (OSError, UnicodeDecodeError, json.JSONDecodeError):
            raise RuntimeError("audit session state is unreadable; refusing implicit reset") from None
        if (not isinstance(value, dict)
                or set(value) != {"state", "session_id", "command_id"}
                or value["state"] not in self.STATES):
            raise RuntimeError("audit session state is invalid")
        return value

    def _write(self, value: dict) -> None:
        fd, temp_name = tempfile.mkstemp(prefix=".audit-session-", dir=str(self.path.parent))
        try:
            os.fchmod(fd, 0o600)
            with os.fdopen(fd, "w", encoding="utf-8") as stream:
                json.dump(value, stream, separators=(",", ":"))
                stream.flush()
                os.fsync(stream.fileno())
            os.replace(temp_name, self.path)
            dir_fd = os.open(str(self.path.parent), os.O_DIRECTORY)
            try:
                os.fsync(dir_fd)
            finally:
                os.close(dir_fd)
        finally:
            if os.path.exists(temp_name):
                os.unlink(temp_name)

    @staticmethod
    def _uuid(value: str, field: str) -> str:
        try:
            normalized = str(uuid.UUID(value))
        except (ValueError, TypeError, AttributeError):
            raise ValueError(f"{field} must be a UUID") from None
        if normalized != value.lower():
            raise ValueError(f"{field} must be a canonical UUID")
        return normalized

    def start(self, session_id: str, command_id: str) -> dict:
        session_id = self._uuid(session_id, "session_id")
        command_id = self._uuid(command_id, "command_id")
        with self._lock:
            current = self._read()
            if current["state"] == "running" and current["session_id"] == session_id:
                return current
            if current["state"] != "stopped":
                if current["state"] == "starting" and current["session_id"] == session_id:
                    return self.reconcile()
                raise AuditSessionConflict("another audit session transition is active")
            intent = {"state": "starting", "session_id": session_id, "command_id": command_id}
            self._write(intent)
            self.ensure_enabled(session_id)
            active = {**intent, "state": "running"}
            self._write(active)
            return active

    def stop(self, session_id: str, command_id: str) -> dict:
        session_id = self._uuid(session_id, "session_id")
        command_id = self._uuid(command_id, "command_id")
        with self._lock:
            current = self._read()
            if current["state"] == "stopped":
                return current
            if current["session_id"] != session_id:
                raise AuditSessionConflict("STOP session does not match the active session")
            if current["state"] == "stopping":
                return self.reconcile()
            if current["state"] != "running":
                raise AuditSessionConflict("audit session is not in a stoppable state")
            intent = {"state": "stopping", "session_id": session_id, "command_id": command_id}
            self._write(intent)
            self.ensure_disabled(session_id)
            stopped = {"state": "stopped", "session_id": None, "command_id": command_id}
            self._write(stopped)
            return stopped

    def reconcile(self) -> dict:
        """Replay an interrupted desired-state transition after process restart."""
        with self._lock:
            current = self._read()
            if current["state"] == "starting":
                self.ensure_enabled(current["session_id"])
                current["state"] = "running"
                self._write(current)
            elif current["state"] == "stopping":
                self.ensure_disabled(current["session_id"])
                current = {
                    "state": "stopped", "session_id": None,
                    "command_id": current["command_id"],
                }
                self._write(current)
            return current

    def status(self) -> dict:
        with self._lock:
            return self._read()
