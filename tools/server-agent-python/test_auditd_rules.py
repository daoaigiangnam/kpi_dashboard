import unittest

from auditd_rules import AuditdRuleConfig, AuditdRuleManager


class AuditdRuleManagerTests(unittest.TestCase):
    def setUp(self):
        self.manager = AuditdRuleManager()

    def test_default_rules_cover_32_and_64_bit_exec(self):
        rendered = self.manager.render()
        self.assertIn("-F arch=b64 -S execve,execveat", rendered)
        self.assertIn("-F arch=b32 -S execve,execveat", rendered)
        self.assertEqual(2, len(rendered.strip().splitlines()))

    def test_default_excludes_unset_login_uid(self):
        self.assertTrue(all("-F auid!=-1" in line for line in self.manager.render().splitlines()))

    def test_can_include_unset_login_uid_explicitly(self):
        rendered = self.manager.render(AuditdRuleConfig(include_unset_login_uid=True))
        self.assertNotIn("auid!=-1", rendered)

    def test_can_limit_architecture(self):
        rendered = self.manager.render(AuditdRuleConfig(architectures=("b64",)))
        self.assertEqual(1, len(rendered.strip().splitlines()))
        self.assertIn("arch=b64", rendered)

    def test_rejects_unsafe_rule_key(self):
        with self.assertRaises(ValueError):
            AuditdRuleConfig(key="bad key; -D")

    def test_rejects_unknown_architecture(self):
        with self.assertRaises(ValueError):
            AuditdRuleConfig(architectures=("arm128",))

    def test_documents_managed_rules_path(self):
        self.assertEqual("/etc/audit/rules.d/50-server-monitoring.rules",
                         self.manager.desired_path())


if __name__ == "__main__":
    unittest.main()
