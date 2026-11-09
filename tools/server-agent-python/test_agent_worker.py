import unittest
import uuid
from unittest.mock import Mock

from agent_worker import AgentWorker
from command_journal import CommandJournal


class AgentWorkerTests(unittest.TestCase):
    def setUp(self):
        self.client = Mock()
        self.client.heartbeat.return_value = {"ok": True}
        self.client.poll_commands.return_value = {"commands": []}
        self.journal = Mock(spec=CommandJournal)
        self.worker = AgentWorker(self.client, self.journal)

    def test_heartbeat_and_empty_poll(self):
        result = self.worker.run_once()
        self.assertEqual({"received": 0, "duplicates": 0, "expired": 0,
                          "terminal_ack_replays": 0}, result)
        self.client.heartbeat.assert_called_once()
        self.client.poll_commands.assert_called_once()

    def test_valid_command_is_persisted_but_not_executed_or_acked(self):
        command = {
            "command_id": str(uuid.uuid4()),
            "type": "AUDIT_START",
            "payload": {"session_id": str(uuid.uuid4())},
            "expires_at": None,
        }
        self.client.poll_commands.return_value = {"commands": [command]}
        self.journal.record_received.return_value = (
            {"command_id": command["command_id"], "state": "received"}, True
        )
        result = self.worker.run_once()
        self.assertEqual(1, result["received"])
        self.journal.record_received.assert_called_once_with(command)
        self.client.acknowledge.assert_not_called()

    def test_duplicate_command_is_not_treated_as_new(self):
        command = {
            "command_id": str(uuid.uuid4()),
            "type": "AUDIT_STOP",
            "payload": {"session_id": str(uuid.uuid4())},
            "expires_at": None,
        }
        self.client.poll_commands.return_value = {"commands": [command]}
        self.journal.record_received.return_value = (
            {"command_id": command["command_id"], "state": "received"}, False
        )
        result = self.worker.run_once()
        self.assertEqual(1, result["duplicates"])
        self.assertEqual(0, result["received"])

    def test_expired_command_is_not_persisted(self):
        command = {
            "command_id": str(uuid.uuid4()),
            "type": "AUDIT_START",
            "payload": {"session_id": str(uuid.uuid4())},
            "expires_at": "2000-01-01T00:00:00+00:00",
        }
        self.client.poll_commands.return_value = {"commands": [command]}
        result = self.worker.run_once()
        self.assertEqual(1, result["expired"])
        self.journal.record_received.assert_not_called()

    def test_existing_executing_command_retries_executing_ack(self):
        command = {
            "command_id": str(uuid.uuid4()),
            "type": "AUDIT_START",
            "payload": {"session_id": str(uuid.uuid4())},
            "expires_at": None,
        }
        self.client.poll_commands.return_value = {"commands": [command]}
        self.journal.record_received.return_value = (
            {"command_id": command["command_id"], "state": "executing",
             "result_json": None, "error": None}, False
        )
        result = self.worker.run_once()
        self.client.acknowledge.assert_called_once_with({
            "command_id": command["command_id"], "status": "executing"
        })
        self.assertEqual(1, result["terminal_ack_replays"])

    def test_heartbeat_must_be_confirmed(self):
        self.client.heartbeat.return_value = {"ok": False}
        with self.assertRaises(RuntimeError):
            self.worker.run_once()
        self.client.poll_commands.assert_not_called()

    def test_invalid_command_response_is_rejected(self):
        self.client.poll_commands.return_value = {"commands": None}
        with self.assertRaises(ValueError):
            self.worker.run_once()


if __name__ == "__main__":
    unittest.main()
