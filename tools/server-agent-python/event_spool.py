"""Durable SQLite spool for audit events awaiting Central acknowledgement."""
from __future__ import annotations

import json
import sqlite3
import threading
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


class EventSpool:
    def __init__(self, database: str | Path, max_events: int = 100_000):
        if max_events < 1:
            raise ValueError("max_events must be positive")
        self.path = Path(database)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.max_events = max_events
        self._lock = threading.RLock()
        self._db = sqlite3.connect(str(self.path), timeout=30,
                                   isolation_level=None, check_same_thread=False)
        self._db.row_factory = sqlite3.Row
        self._db.execute("PRAGMA journal_mode=WAL")
        self._db.execute("PRAGMA synchronous=FULL")
        self._db.execute("""
            CREATE TABLE IF NOT EXISTS audit_event_spool (
                event_key TEXT PRIMARY KEY,
                session_id TEXT NOT NULL,
                event_json TEXT NOT NULL,
                queued_at TEXT NOT NULL
            )
        """)

    def enqueue(self, session_id: str, event: dict[str, Any]) -> bool:
        key = event.get("event_key")
        if not isinstance(key, str) or not key or len(key) > 190:
            raise ValueError("event_key must be a non-empty string up to 190 chars")
        if not isinstance(session_id, str) or not session_id:
            raise ValueError("session_id is required")
        encoded = json.dumps(event, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
        with self._lock:
            self._db.execute("BEGIN IMMEDIATE")
            try:
                exists = self._db.execute(
                    "SELECT 1 FROM audit_event_spool WHERE event_key=?", (key,)
                ).fetchone()
                if exists:
                    self._db.execute("COMMIT")
                    return False
                count = self._db.execute(
                    "SELECT COUNT(*) AS n FROM audit_event_spool"
                ).fetchone()["n"]
                if count >= self.max_events:
                    raise OverflowError("audit event spool capacity reached")
                self._db.execute("""
                    INSERT INTO audit_event_spool(event_key, session_id, event_json, queued_at)
                    VALUES (?, ?, ?, ?)
                """, (key, session_id, encoded, datetime.now(timezone.utc).isoformat()))
                self._db.execute("COMMIT")
                return True
            except Exception:
                self._db.execute("ROLLBACK")
                raise

    def peek_batch(self, session_id: str, limit: int = 100) -> list[dict[str, Any]]:
        if limit < 1 or limit > 500:
            raise ValueError("batch limit must be between 1 and 500")
        rows = self._db.execute("""
            SELECT event_json FROM audit_event_spool
            WHERE session_id=? ORDER BY queued_at, event_key LIMIT ?
        """, (session_id, limit)).fetchall()
        return [json.loads(row["event_json"]) for row in rows]

    def acknowledge_batch(self, session_id: str, events: list[dict[str, Any]]) -> int:
        keys = [event.get("event_key") for event in events]
        if any(not isinstance(key, str) or not key for key in keys):
            raise ValueError("all acknowledged events must have event_key")
        if not keys:
            return 0
        with self._lock:
            self._db.execute("BEGIN IMMEDIATE")
            try:
                placeholders = ",".join("?" for _ in keys)
                cursor = self._db.execute(
                    f"DELETE FROM audit_event_spool WHERE session_id=? AND event_key IN ({placeholders})",
                    [session_id, *keys],
                )
                self._db.execute("COMMIT")
                return cursor.rowcount
            except Exception:
                self._db.execute("ROLLBACK")
                raise

    def pending_count(self, session_id: str | None = None) -> int:
        if session_id is None:
            row = self._db.execute("SELECT COUNT(*) AS n FROM audit_event_spool").fetchone()
        else:
            row = self._db.execute(
                "SELECT COUNT(*) AS n FROM audit_event_spool WHERE session_id=?", (session_id,)
            ).fetchone()
        return int(row["n"])

    def close(self) -> None:
        with self._lock:
            self._db.close()
