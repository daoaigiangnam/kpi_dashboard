"""Durable, local command journal for the Common Server Agent.

This module records command identity and outcomes. It deliberately does not
execute commands; execution belongs to a narrowly allowlisted reconciler.
"""
from __future__ import annotations

import hashlib
import json
import sqlite3
import threading
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


ALLOWED_COMMANDS = {"AUDIT_START", "AUDIT_STOP"}
TERMINAL_STATES = {"completed", "failed"}


class CommandConflict(ValueError):
    """A command_id was reused with different immutable command content."""


class CommandJournal:
    def __init__(self, database: str | Path):
        self.path = Path(database)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self._lock = threading.RLock()
        self._connection = sqlite3.connect(
            str(self.path), timeout=30, isolation_level=None, check_same_thread=False
        )
        self._connection.row_factory = sqlite3.Row
        self._connection.execute("PRAGMA journal_mode=WAL")
        self._connection.execute("PRAGMA synchronous=FULL")
        self._connection.execute("PRAGMA foreign_keys=ON")
        self._connection.execute("""
            CREATE TABLE IF NOT EXISTS agent_commands (
                command_id TEXT PRIMARY KEY,
                command_type TEXT NOT NULL,
                session_id TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                payload_sha256 TEXT NOT NULL,
                state TEXT NOT NULL CHECK(state IN
                    ('received','executing','completed','failed')),
                received_at TEXT NOT NULL,
                started_at TEXT,
                finished_at TEXT,
                last_ack_at TEXT,
                result_json TEXT,
                error TEXT,
                attempt_count INTEGER NOT NULL DEFAULT 0
            )
        """)

    @staticmethod
    def _now() -> str:
        return datetime.now(timezone.utc).isoformat()

    @staticmethod
    def _canonical(value: Any) -> str:
        return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False)

    def record_received(self, command: dict[str, Any]) -> tuple[dict[str, Any], bool]:
        """Persist a validated command. Returns (journal row, is_new)."""
        command_id = str(command.get("command_id", ""))
        try:
            if str(uuid.UUID(command_id)) != command_id.lower():
                raise ValueError
        except (ValueError, AttributeError):
            raise ValueError("command_id must be a UUID") from None

        command_type = command.get("type")
        if command_type not in ALLOWED_COMMANDS:
            raise ValueError("unsupported command type")
        payload = command.get("payload")
        if not isinstance(payload, dict):
            raise ValueError("payload must be an object")
        session_id = payload.get("session_id")
        try:
            session_id = str(uuid.UUID(str(session_id)))
        except (ValueError, AttributeError):
            raise ValueError("payload.session_id must be a UUID") from None

        payload_json = self._canonical(payload)
        digest = hashlib.sha256(payload_json.encode("utf-8")).hexdigest()
        with self._lock:
            self._connection.execute("BEGIN IMMEDIATE")
            try:
                row = self._connection.execute(
                    "SELECT * FROM agent_commands WHERE command_id=?", (command_id,)
                ).fetchone()
                if row:
                    if row["command_type"] != command_type or row["payload_sha256"] != digest:
                        raise CommandConflict("command_id reused with different command content")
                    self._connection.execute("COMMIT")
                    return dict(row), False

                self._connection.execute("""
                    INSERT INTO agent_commands
                    (command_id, command_type, session_id, payload_json, payload_sha256,
                     state, received_at)
                    VALUES (?, ?, ?, ?, ?, 'received', ?)
                """, (command_id, command_type, session_id, payload_json, digest, self._now()))
                row = self._connection.execute(
                    "SELECT * FROM agent_commands WHERE command_id=?", (command_id,)
                ).fetchone()
                self._connection.execute("COMMIT")
                return dict(row), True
            except Exception:
                self._connection.execute("ROLLBACK")
                raise

    def mark_executing(self, command_id: str) -> dict[str, Any]:
        with self._lock:
            self._connection.execute("BEGIN IMMEDIATE")
            try:
                row = self._connection.execute(
                    "SELECT * FROM agent_commands WHERE command_id=?", (command_id,)
                ).fetchone()
                if row is None:
                    raise KeyError(command_id)
                if row["state"] == "received":
                    self._connection.execute("""
                        UPDATE agent_commands SET state='executing', started_at=?, attempt_count=attempt_count+1
                        WHERE command_id=?
                    """, (self._now(), command_id))
                result = self._connection.execute(
                    "SELECT * FROM agent_commands WHERE command_id=?", (command_id,)
                ).fetchone()
                self._connection.execute("COMMIT")
                return dict(result)
            except Exception:
                self._connection.execute("ROLLBACK")
                raise

    def finish(self, command_id: str, state: str, result: dict[str, Any] | None = None,
               error: str | None = None) -> dict[str, Any]:
        if state not in TERMINAL_STATES:
            raise ValueError("terminal state must be completed or failed")
        result_json = self._canonical(result) if result is not None else None
        with self._lock:
            self._connection.execute("BEGIN IMMEDIATE")
            try:
                row = self._connection.execute(
                    "SELECT * FROM agent_commands WHERE command_id=?", (command_id,)
                ).fetchone()
                if row is None:
                    raise KeyError(command_id)
                if row["state"] in TERMINAL_STATES:
                    self._connection.execute("COMMIT")
                    return dict(row)
                if row["state"] != "executing":
                    raise ValueError("command must be executing before terminal result")
                self._connection.execute("""
                    UPDATE agent_commands SET state=?, finished_at=?, result_json=?, error=?
                    WHERE command_id=?
                """, (state, self._now(), result_json, error, command_id))
                updated = self._connection.execute(
                    "SELECT * FROM agent_commands WHERE command_id=?", (command_id,)
                ).fetchone()
                self._connection.execute("COMMIT")
                return dict(updated)
            except Exception:
                self._connection.execute("ROLLBACK")
                raise

    def pending_acknowledgements(self) -> list[dict[str, Any]]:
        rows = self._connection.execute("""
            SELECT * FROM agent_commands
            WHERE state IN ('executing','completed','failed')
            ORDER BY received_at
        """).fetchall()
        return [dict(row) for row in rows]

    def close(self) -> None:
        with self._lock:
            self._connection.close()
