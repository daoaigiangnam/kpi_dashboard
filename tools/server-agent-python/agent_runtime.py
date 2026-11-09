"""Composable runtime cycle for heartbeat, audit ingestion and HTTPS delivery.

The active audit session and privileged reader are supplied by the service
bootstrap after Central configuration/session validation. This class does not
start or stop auditd and never executes arbitrary commands.
"""
from __future__ import annotations

from typing import Any

from agent_worker import AgentWorker


class AgentRuntime:
    def __init__(
        self,
        worker: AgentWorker,
        reader: Any,
        spool: Any,
        session_id: str,
        read_max_bytes: int = 262144,
        upload_batch_size: int = 100,
        session_manager: Any | None = None,
    ):
        if not isinstance(session_id, str) or not session_id.strip():
            raise ValueError("an active audit session_id is required")
        if read_max_bytes < 1 or read_max_bytes > 4 * 1024 * 1024:
            raise ValueError("read_max_bytes must be between 1 and 4194304")
        if upload_batch_size < 1 or upload_batch_size > 500:
            raise ValueError("upload_batch_size must be between 1 and 500")
        self.worker = worker
        self.reader = reader
        self.spool = spool
        self.session_id = session_id
        self.read_max_bytes = read_max_bytes
        self.upload_batch_size = upload_batch_size
        self.session_manager = session_manager

    def run_once(self) -> dict[str, Any]:
        """Run one supervised cycle; cursor advances only after durable spool."""
        control = self.worker.run_once()
        active_session_id = self.session_id
        should_ingest = True
        if self.session_manager is not None:
            state = self.session_manager.status()
            should_ingest = state["state"] == "running"
            if should_ingest:
                active_session_id = state["session_id"]

        ingestion = {"lines": 0, "events_inserted": 0}
        if should_ingest:
            ingestion = self.worker.ingest_audit_log_batch(
                self.reader,
                self.spool,
                active_session_id,
                max_bytes=self.read_max_bytes,
            )
        # Flush the configured/previous session spool even after STOP. Never
        # read new audit records while no session is actively running.
        uploaded = self.worker.flush_events(
            self.spool,
            active_session_id,
            batch_size=self.upload_batch_size,
        )
        return {
            "control": control,
            "audit": ingestion,
            "uploaded": uploaded,
            "session_active": should_ingest,
            "session_id": active_session_id,
            "pending": self.spool.pending_count(active_session_id),
        }

    def close(self) -> None:
        """Close local durable resources; caller invokes after supervisor exits."""
        try:
            self.spool.close()
        finally:
            journal = getattr(self.worker, "journal", None)
            close = getattr(journal, "close", None)
            if callable(close):
                close()
