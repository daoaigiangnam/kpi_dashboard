"""Least-privilege incremental reader for the audit log file.

This module reads only the configured audit log. It never invokes a shell,
changes audit rules, or attempts privilege escalation. Cursor advances only
after the caller explicitly commits successfully spooled lines.
"""
from __future__ import annotations

import json
import os
import tempfile
from pathlib import Path


class AuditLogIdentityChanged(RuntimeError):
    """The log inode changed; caller must drain/resolve rotation before continuing."""


class AuditLogReader:
    def __init__(self, log_path: str | Path, cursor_path: str | Path):
        self.log_path = Path(log_path)
        self.cursor_path = Path(cursor_path)
        self.cursor_path.parent.mkdir(parents=True, exist_ok=True)

    def _load_cursor(self) -> dict:
        if not self.cursor_path.exists():
            return {"device": None, "inode": None, "offset": 0}
        try:
            data = json.loads(self.cursor_path.read_text(encoding="utf-8"))
            if not isinstance(data, dict) or not isinstance(data.get("offset"), int) or data["offset"] < 0:
                raise ValueError
            return data
        except (OSError, ValueError, json.JSONDecodeError):
            raise RuntimeError("audit reader cursor is invalid; refusing to reset position") from None

    def read_batch(self, max_bytes: int = 262144) -> tuple[list[str], dict]:
        if max_bytes < 1 or max_bytes > 4 * 1024 * 1024:
            raise ValueError("max_bytes must be between 1 and 4194304")
        stat = self.log_path.stat()
        cursor = self._load_cursor()
        identity = (stat.st_dev, stat.st_ino)
        previous = (cursor.get("device"), cursor.get("inode"))
        if previous != (None, None) and previous != identity:
            raise AuditLogIdentityChanged("audit log identity changed; rotation recovery is required")
        if stat.st_size < cursor["offset"]:
            raise AuditLogIdentityChanged("audit log was truncated; rotation recovery is required")
        with self.log_path.open("rb") as stream:
            stream.seek(cursor["offset"])
            raw = stream.read(max_bytes)
            end_offset = stream.tell()
        # Process only complete newline-terminated records; retain partial tail.
        complete_end = raw.rfind(b"\n") + 1
        complete = raw[:complete_end]
        next_cursor = {
            "device": stat.st_dev,
            "inode": stat.st_ino,
            "offset": cursor["offset"] + complete_end,
        }
        lines = [line.decode("utf-8", errors="replace") for line in complete.splitlines()]
        return lines, next_cursor

    def commit(self, cursor: dict) -> None:
        required = {"device", "inode", "offset"}
        if set(cursor) != required or not isinstance(cursor["offset"], int) or cursor["offset"] < 0:
            raise ValueError("invalid audit reader cursor")
        fd, temp_name = tempfile.mkstemp(prefix=".audit-cursor-", dir=str(self.cursor_path.parent))
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as stream:
                json.dump(cursor, stream, separators=(",", ":"))
                stream.flush()
                os.fsync(stream.fileno())
            os.replace(temp_name, self.cursor_path)
        finally:
            if os.path.exists(temp_name):
                os.unlink(temp_name)
