import tempfile
import unittest
import uuid
from pathlib import Path

from command_journal import CommandConflict, CommandJournal


class CommandJournalTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.journal = CommandJournal(Path(self.tmp.name) / "agent.sqlite3")
        self.command = {
            "command_id": str(uuid.uuid4()),
            "type": "AUDIT_START",
            "payload": {"session_id": str(uuid.uuid4()), "enabled": True},
        }

    def tearDown(self):
        self.journal.close()
        self.tmp.cleanup()

    def test_duplicate_delivery_is_not_new_and_keeps_same_record(self):
        first, is_new = self.journal.record_received(self.command)
        second, is_new_again = self.journal.record_received(self.command)
        self.assertTrue(is_new)
        self.assertFalse(is_new_again)
        self.assertEqual(first["command_id"], second["command_id"])
        self.assertEqual("received", second["state"])

    def test_reused_id_with_different_payload_is_rejected(self):
        self.journal.record_received(self.command)
        changed = {**self.command, "payload": {**self.command["payload"], "enabled": False}}
        with self.assertRaises(CommandConflict):
            self.journal.record_received(changed)

    def test_unknown_command_type_is_rejected(self):
        invalid = {**self.command, "type": "RUN_SHELL"}
        with self.assertRaises(ValueError):
            self.journal.record_received(invalid)

    def test_terminal_result_is_durable_and_immutable(self):
        self.journal.record_received(self.command)
        self.journal.mark_executing(self.command["command_id"])
        first = self.journal.finish(self.command["command_id"], "completed", {"exit_code": 0})
        replay = self.journal.finish(self.command["command_id"], "failed", {"exit_code": 99}, "retry")
        self.assertEqual("completed", first["state"])
        self.assertEqual("completed", replay["state"])
        self.assertEqual('{"exit_code":0}', replay["result_json"])
        self.assertIsNone(replay["error"])

    def test_executing_state_survives_agent_restart(self):
        self.journal.record_received(self.command)
        self.journal.mark_executing(self.command["command_id"])
        self.journal.close()
        self.journal = CommandJournal(Path(self.tmp.name) / "agent.sqlite3")
        pending = self.journal.pending_acknowledgements()
        self.assertEqual(1, len(pending))
        self.assertEqual("executing", pending[0]["state"])


if __name__ == "__main__":
    unittest.main()
