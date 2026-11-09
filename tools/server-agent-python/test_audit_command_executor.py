import tempfile
import unittest
import uuid
from pathlib import Path
from unittest.mock import Mock

from audit_command_executor import AuditCommandExecutor
from audit_session import AuditSessionManager
from command_journal import CommandJournal


class AuditCommandExecutorTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        root = Path(self.tmp.name)
        self.journal = CommandJournal(root / "commands.sqlite3")
        self.addCleanup(self.journal.close)
        self.enabled = []
        self.disabled = []
        self.sessions = AuditSessionManager(
            root / "session.json",
            lambda session: self.enabled.append(session),
            lambda session: self.disabled.append(session),
        )
        self.client = Mock()
        self.client.acknowledge.return_value = {"ok": True}
        self.executor = AuditCommandExecutor(self.journal, self.sessions, self.client)
        self.session_id = str(uuid.uuid4())
        self.command = {
            "command_id": str(uuid.uuid4()),
            "type": "AUDIT_START",
            "payload": {"session_id": self.session_id},
            "expires_at": None,
        }

    def test_start_persists_execution_and_terminal_ack(self):
        row = self.executor.execute(self.command)
        self.assertEqual("completed", row["state"])
        self.assertEqual([self.session_id], self.enabled)
        self.assertEqual(
            ["executing", "completed"],
            [call.args[0]["status"] for call in self.client.acknowledge.call_args_list],
        )
        self.assertEqual("running", self.sessions.status()["state"])

    def test_terminal_replay_only_retries_terminal_ack(self):
        self.executor.execute(self.command)
        self.client.acknowledge.reset_mock()
        row = self.executor.execute(self.command)
        self.assertEqual("completed", row["state"])
        self.client.acknowledge.assert_called_once()
        self.assertEqual("completed", self.client.acknowledge.call_args.args[0]["status"])
        self.assertEqual([self.session_id], self.enabled)

    def test_lost_terminal_ack_is_retried_without_repeating_effect(self):
        self.client.acknowledge.side_effect = [
            {"ok": True},
            ConnectionError("response lost"),
            {"ok": True},
        ]
        with self.assertRaises(ConnectionError):
            self.executor.execute(self.command)
        self.assertEqual("completed", self.journal.record_received(self.command)[0]["state"])
        self.client.acknowledge.side_effect = None
        self.client.acknowledge.return_value = {"ok": True}
        self.executor.execute(self.command)
        self.assertEqual([self.session_id], self.enabled)
        self.assertEqual("completed", self.client.acknowledge.call_args.args[0]["status"])

    def test_stop_command_uses_matching_active_session(self):
        self.executor.execute(self.command)
        stop = {
            "command_id": str(uuid.uuid4()),
            "type": "AUDIT_STOP",
            "payload": {"session_id": self.session_id},
            "expires_at": None,
        }
        row = self.executor.execute(stop)
        self.assertEqual("completed", row["state"])
        self.assertEqual([self.session_id], self.disabled)
        self.assertEqual("stopped", self.sessions.status()["state"])

    def test_failed_transition_is_terminal_and_acknowledged(self):
        bad = {
            "command_id": str(uuid.uuid4()),
            "type": "AUDIT_STOP",
            "payload": {"session_id": str(uuid.uuid4())},
            "expires_at": None,
        }
        row = self.executor.execute(bad)
        self.assertEqual("failed", row["state"])
        self.assertIn("AuditSessionConflict", row["error"])
        self.assertEqual("failed", self.client.acknowledge.call_args.args[0]["status"])


if __name__ == "__main__":
    unittest.main()
