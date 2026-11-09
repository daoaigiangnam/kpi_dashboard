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

    def test_optional_command_executor_receives_durable_command(self):
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
        executor = Mock()
        worker = AgentWorker(self.client, self.journal, command_executor=executor)
        result = worker.run_once()
        executor.execute.assert_called_once_with(command)
        self.client.acknowledge.assert_not_called()
        self.assertEqual(1, result["received"])

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

    def test_ingest_auditd_raw_parses_and_spools_execution_event(self):
        import tempfile
        from pathlib import Path
        from event_spool import EventSpool

        lines = [
            'type=SYSCALL msg=audit(1794225600.123:9010): syscall=59 success=yes exit=0 pid=20 ppid=10 auid=1000 uid=1000 comm="id" exe="/usr/bin/id"',
            'type=EXECVE msg=audit(1794225600.123:9010): argc=1 a0="id"',
        ]
        with tempfile.TemporaryDirectory() as tmp:
            spool = EventSpool(Path(tmp) / "spool.sqlite3")
            count = self.worker.ingest_auditd_raw(lines, "session-1", spool)
            self.assertEqual(1, count)
            event = spool.peek_batch("session-1")[0]
            self.assertEqual("audit:9010", event["event_key"])
            self.assertEqual("/usr/bin/id", event["exe"])
            self.assertEqual(0, self.worker.ingest_auditd_raw(lines, "session-1", spool))
            self.assertEqual(1, spool.pending_count("session-1"))
            spool.close()

    def test_reader_parser_spool_pipeline_commits_cursor_after_durable_enqueue(self):
        import tempfile
        from pathlib import Path
        from audit_log_reader import AuditLogReader
        from event_spool import EventSpool

        raw = (
            'type=SYSCALL msg=audit(1794225600.123:9011): syscall=59 success=yes exit=0 pid=20 ppid=10 auid=1000 uid=1000 comm="id" exe="/usr/bin/id"\n'
            'type=EXECVE msg=audit(1794225600.123:9011): argc=1 a0="id"\n'
        )
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            log = root / "audit.log"
            log.write_text(raw, encoding="utf-8")
            reader = AuditLogReader(log, root / "state" / "cursor.json")
            spool = EventSpool(root / "spool.sqlite3")

            result = self.worker.ingest_audit_log_batch(reader, spool, "session-pipeline")
            self.assertEqual({"lines": 2, "events_inserted": 1}, result)
            self.assertEqual(1, spool.pending_count("session-pipeline"))
            self.assertEqual("audit:9011", spool.peek_batch("session-pipeline")[0]["event_key"])

            replay = self.worker.ingest_audit_log_batch(reader, spool, "session-pipeline")
            self.assertEqual({"lines": 0, "events_inserted": 0}, replay)
            self.assertEqual(1, spool.pending_count("session-pipeline"))
            spool.close()

    def test_reader_cursor_is_not_committed_when_spooling_fails(self):
        from unittest.mock import Mock
        reader = Mock()
        reader.read_batch.return_value = ([
            'type=SYSCALL msg=audit(1794225600.123:9012): syscall=59 success=yes exit=0 pid=20 ppid=10 auid=1000 uid=1000 comm="id" exe="/usr/bin/id"'
        ], {"device": 1, "inode": 2, "offset": 100, "source_path": "/var/log/audit/audit.log"})
        spool = Mock()
        spool.enqueue.side_effect = OverflowError("spool full")
        with self.assertRaises(OverflowError):
            self.worker.ingest_audit_log_batch(reader, spool, "session-failure")
        reader.commit.assert_not_called()

    def test_flush_events_deletes_batch_only_after_central_success(self):
        import tempfile
        from pathlib import Path
        from event_spool import EventSpool

        with tempfile.TemporaryDirectory() as tmp:
            spool = EventSpool(Path(tmp) / "spool.sqlite3")
            event = {"event_key": "evt-1", "event_time": "2026-11-09T10:00:00Z"}
            spool.enqueue("session-1", event)
            self.client.upload_events.return_value = {"ok": True, "accepted": 1}
            removed = self.worker.flush_events(spool, "session-1")
            self.assertEqual(1, removed)
            self.assertEqual(0, spool.pending_count("session-1"))
            self.client.upload_events.assert_called_once_with({
                "session_id": "session-1", "events": [event]
            })
            spool.close()

    def test_flush_events_retains_batch_when_central_does_not_confirm(self):
        import tempfile
        from pathlib import Path
        from event_spool import EventSpool

        with tempfile.TemporaryDirectory() as tmp:
            spool = EventSpool(Path(tmp) / "spool.sqlite3")
            spool.enqueue("session-1", {"event_key": "evt-2"})
            self.client.upload_events.return_value = {"ok": False}
            with self.assertRaises(RuntimeError):
                self.worker.flush_events(spool, "session-1")
            self.assertEqual(1, spool.pending_count("session-1"))
            spool.close()

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
