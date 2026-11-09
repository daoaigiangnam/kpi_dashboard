import subprocess
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace

from auditd_adapter import AuditdAdapter, AuditdAdapterError, AuditdImmutableError


class FakeRunner:
    def __init__(self):
        self.calls = []
        self.enabled = 1
        self.rules = []

    def __call__(self, argv, **kwargs):
        self.calls.append((argv, kwargs))
        if argv[-1] == "-s":
            return SimpleNamespace(returncode=0, stdout=f"enabled {self.enabled}\n", stderr="")
        if argv[-1] == "--load":
            return SimpleNamespace(returncode=0, stdout="", stderr="")
        if argv[-1] == "-l":
            return SimpleNamespace(returncode=0, stdout="\n".join(self.rules), stderr="")
        return SimpleNamespace(returncode=1, stdout="", stderr="failure")


class AuditdAdapterTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.rules_dir = Path(self.tmp.name) / "rules.d"
        self.rules_dir.mkdir()
        self.rule_path = self.rules_dir / "50-server-monitoring.rules"
        self.runner = FakeRunner()
        self.adapter = AuditdAdapter(self.rule_path, runner=self.runner)

    def test_enable_writes_only_managed_rules_and_verifies_kernel_state(self):
        expected = self.adapter.manager.render(self.adapter.config).strip().splitlines()
        self.runner.rules = expected
        self.adapter.ensure_enabled("session-1")
        self.assertEqual(expected, self.rule_path.read_text().strip().splitlines())
        self.assertEqual(0o640, self.rule_path.stat().st_mode & 0o777)
        self.assertEqual(["/sbin/auditctl", "/sbin/augenrules", "/sbin/auditctl"],
                         [call[0][0] for call in self.runner.calls])
        self.assertTrue(all(call[1]["shell"] is False for call in self.runner.calls))

    def test_enable_fails_if_rules_are_not_loaded(self):
        with self.assertRaises(AuditdAdapterError):
            self.adapter.ensure_enabled("session-1")

    def test_disable_removes_only_managed_file_and_verifies_absence(self):
        self.rule_path.write_text(self.adapter.manager.render())
        self.runner.rules = ["-a always,exit -F arch=b64 -S execve -F key=other"]
        self.adapter.ensure_disabled("session-1")
        self.assertFalse(self.rule_path.exists())

    def test_immutable_kernel_rejects_changes_before_file_mutation(self):
        self.runner.enabled = 2
        with self.assertRaises(AuditdImmutableError):
            self.adapter.ensure_enabled("session-1")
        self.assertFalse(self.rule_path.exists())
        self.assertEqual(1, len(self.runner.calls))

    def test_rejects_symlinked_managed_file(self):
        target = self.rules_dir / "target"
        target.write_text("do not touch")
        self.rule_path.symlink_to(target)
        with self.assertRaises(AuditdAdapterError):
            self.adapter.ensure_enabled("session-1")
        self.assertEqual("do not touch", target.read_text())

    def test_never_uses_shell_or_central_supplied_command(self):
        self.runner.rules = self.adapter.manager.render().strip().splitlines()
        self.adapter.ensure_enabled("session-1")
        for argv, kwargs in self.runner.calls:
            self.assertIsInstance(argv, list)
            self.assertNotIn("session-1", argv)
            self.assertFalse(kwargs["shell"])


if __name__ == "__main__":
    unittest.main()
