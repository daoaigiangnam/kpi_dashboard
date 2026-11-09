import unittest
from unittest.mock import Mock

from agent_runtime import AgentRuntime


class AgentRuntimeTests(unittest.TestCase):
    def setUp(self):
        self.worker = Mock()
        self.worker.run_once.return_value = {"received": 0}
        self.worker.ingest_audit_log_batch.return_value = {
            "lines": 2, "events_inserted": 1
        }
        self.worker.flush_events.return_value = 1
        self.reader = Mock()
        self.spool = Mock()
        self.spool.pending_count.return_value = 0

    def test_runtime_runs_control_ingestion_and_upload_in_order(self):
        runtime = AgentRuntime(self.worker, self.reader, self.spool, "session-1")
        result = runtime.run_once()

        self.worker.run_once.assert_called_once_with()
        self.worker.ingest_audit_log_batch.assert_called_once_with(
            self.reader, self.spool, "session-1", max_bytes=262144
        )
        self.worker.flush_events.assert_called_once_with(
            self.spool, "session-1", batch_size=100
        )
        self.spool.pending_count.assert_called_once_with("session-1")
        self.assertEqual({
            "control": {"received": 0},
            "audit": {"lines": 2, "events_inserted": 1},
            "uploaded": 1,
            "pending": 0,
        }, result)

    def test_control_plane_failure_prevents_cursor_advance(self):
        self.worker.run_once.side_effect = ConnectionError("Central offline")
        runtime = AgentRuntime(self.worker, self.reader, self.spool, "session-1")
        with self.assertRaises(ConnectionError):
            runtime.run_once()
        self.worker.ingest_audit_log_batch.assert_not_called()
        self.worker.flush_events.assert_not_called()

    def test_ingestion_failure_prevents_upload(self):
        self.worker.ingest_audit_log_batch.side_effect = OverflowError("spool full")
        runtime = AgentRuntime(self.worker, self.reader, self.spool, "session-1")
        with self.assertRaises(OverflowError):
            runtime.run_once()
        self.worker.flush_events.assert_not_called()

    def test_requires_session_and_valid_limits(self):
        with self.assertRaises(ValueError):
            AgentRuntime(self.worker, self.reader, self.spool, "")
        with self.assertRaises(ValueError):
            AgentRuntime(self.worker, self.reader, self.spool, "s", read_max_bytes=0)
        with self.assertRaises(ValueError):
            AgentRuntime(self.worker, self.reader, self.spool, "s", upload_batch_size=501)

    def test_close_closes_spool(self):
        runtime = AgentRuntime(self.worker, self.reader, self.spool, "session-1")
        runtime.close()
        self.spool.close.assert_called_once_with()


if __name__ == "__main__":
    unittest.main()
