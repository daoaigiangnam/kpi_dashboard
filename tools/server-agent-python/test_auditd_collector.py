import unittest

from auditd_collector import AuditdRawParser


class AuditdRawParserTests(unittest.TestCase):
    def setUp(self):
        self.parser = AuditdRawParser()

    def test_groups_syscall_execve_and_proctitle_by_serial(self):
        lines = [
            'type=SYSCALL msg=audit(1794225600.123:9001): arch=c000003e syscall=59 success=yes exit=0 ppid=100 pid=200 auid=1000 uid=1000 euid=1000 gid=1000 tty=pts0 comm="id" exe="/usr/bin/id"',
            'type=EXECVE msg=audit(1794225600.123:9001): argc=2 a0="id" a1="-u"',
            'type=PROCTITLE msg=audit(1794225600.123:9001): proctitle=6964002d75',
            'type=CWD msg=audit(1794225600.123:9001): cwd="/home/user"',
        ]
        events = self.parser.parse(lines, "session-1")
        self.assertEqual(1, len(events))
        event = events[0]
        self.assertEqual("audit:9001", event["event_key"])
        self.assertEqual("/usr/bin/id", event["exe"])
        self.assertEqual(["id", "-u"], event["arguments"])
        self.assertEqual("yes", event["success"])
        self.assertEqual("1000", event["auid"])
        self.assertEqual(9001, event["audit_serial"])

    def test_ignores_non_execution_and_unrelated_lines(self):
        lines = [
            'type=USER_LOGIN msg=audit(1794225600.123:9002): pid=100 uid=1000',
            'noise without audit record',
        ]
        self.assertEqual([], self.parser.parse(lines, "session-1"))

    def test_keeps_failed_execve_as_event(self):
        lines = [
            'type=SYSCALL msg=audit(1794225600.123:9003): syscall=59 success=no exit=-2 pid=1 ppid=0 comm="missing" exe="/usr/bin/missing"',
            'type=EXECVE msg=audit(1794225600.123:9003): argc=1 a0="missing"',
        ]
        event = self.parser.parse(lines, "session-1")[0]
        self.assertEqual("no", event["success"])
        self.assertEqual("-2", event["exit_code"])

    def test_decodes_proctitle_when_execve_record_is_absent(self):
        lines = [
            'type=SYSCALL msg=audit(1794225600.123:9004): syscall=59 success=yes exit=0 pid=1 ppid=0 exe="/bin/ls"',
            'type=PROCTITLE msg=audit(1794225600.123:9004): proctitle=6c73002d6c',
        ]
        event = self.parser.parse(lines, "session-1")[0]
        self.assertEqual(["ls", "-l"], event["arguments"])

    def test_requires_session_id(self):
        with self.assertRaises(ValueError):
            self.parser.parse([], "")


if __name__ == "__main__":
    unittest.main()
