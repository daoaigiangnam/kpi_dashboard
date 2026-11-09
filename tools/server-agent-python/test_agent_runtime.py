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
        self.spool.pending_sessions.return_value = ["session-1"]

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
        self.spool.pending_count.assert_called_once_with()
        self.assertEqual({
            "control": {"received": 0},
            "audit": {"lines": 2, "events_inserted": 1},
            "uploaded": 1,
            "session_active": True,
            "session_id": "session-1",
            "session_active": True,
            "session_id": "session-1",
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


    def test_stopped_session_does_not_ingest_but_flushes_pending_spool(self):
        sessions = Mock()
        sessions.status.return_value = {"state": "stopped", "session_id": None}
        self.spool.pending_sessions.return_value = ["session-previous"]
        runtime = AgentRuntime(
            self.worker, self.reader, self.spool, "session-previous",
            session_manager=sessions,
        )
        result = runtime.run_once()

        self.worker.ingest_audit_log_batch.assert_not_called()
        self.worker.flush_events.assert_called_once_with(
            self.spool, "session-previous", batch_size=100
        )
        self.assertEqual(0, result["audit"]["lines"])
        self.assertFalse(result["session_active"])
        self.assertIsNone(result["session_id"])

    def test_running_session_uses_session_manager_identity(self):
        sessions = Mock()
        sessions.status.return_value = {"state": "running", "session_id": "session-active"}
        self.spool.pending_sessions.return_value = ["session-active"]
        runtime = AgentRuntime(
            self.worker, self.reader, self.spool, "session-configured",
            session_manager=sessions,
        )
        result = runtime.run_once()

        self.worker.ingest_audit_log_batch.assert_called_once_with(
            self.reader, self.spool, "session-active", max_bytes=262144
        )
        self.worker.flush_events.assert_called_once_with(
            self.spool, "session-active", batch_size=100
        )
        self.assertEqual("session-active", result["session_id"])


    def test_flushes_all_pending_sessions_after_restart(self):
        sessions = Mock()
        sessions.status.return_value = {"state": "stopped", "session_id": None}
        self.spool.pending_sessions.return_value = ["old-session", "newer-session"]
        runtime = AgentRuntime(
            self.worker, self.reader, self.spool, "configured-session",
            session_manager=sessions,
        )
        result = runtime.run_once()

        self.worker.ingest_audit_log_batch.assert_not_called()
        self.assertEqual(2, self.worker.flush_events.call_count)
        self.worker.flush_events.assert_any_call(
            self.spool, "old-session", batch_size=100
        )
        self.worker.flush_events.assert_any_call(
            self.spool, "newer-session", batch_size=100
        )
        self.assertEqual(2, result["uploaded"])
        self.spool.pending_count.assert_called_once_with()

    def test_close_closes_spool(self):
        runtime = AgentRuntime(self.worker, self.reader, self.spool, "session-1")
        runtime.close()
        self.spool.close.assert_called_once_with()


if __name__ == "__main__":
    unittest.main()
