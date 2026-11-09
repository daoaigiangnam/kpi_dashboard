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

    def run_once(self) -> dict[str, Any]:
        """Run one supervised cycle; cursor advances only after durable spool."""
        control = self.worker.run_once()
        ingestion = self.worker.ingest_audit_log_batch(
            self.reader,
            self.spool,
            self.session_id,
            max_bytes=self.read_max_bytes,
        )
        uploaded = self.worker.flush_events(
            self.spool,
            self.session_id,
            batch_size=self.upload_batch_size,
        )
        return {
            "control": control,
            "audit": ingestion,
            "uploaded": uploaded,
            "pending": self.spool.pending_count(self.session_id),
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
