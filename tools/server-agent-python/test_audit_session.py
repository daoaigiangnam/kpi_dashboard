import json
import tempfile
import unittest
import uuid
from pathlib import Path

from audit_session import AuditSessionConflict, AuditSessionManager


class AuditSessionManagerTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.path = Path(self.tmp.name) / "session.json"
        self.enabled = []
        self.disabled = []
        self.manager = AuditSessionManager(
            self.path,
            lambda session: self.enabled.append(session),
            lambda session: self.disabled.append(session),
        )
        self.session = str(uuid.uuid4())
        self.start_command = str(uuid.uuid4())
        self.stop_command = str(uuid.uuid4())

    def test_start_and_stop_are_idempotent(self):
        started = self.manager.start(self.session, self.start_command)
        replayed = self.manager.start(self.session, self.start_command)
        self.assertEqual("running", started["state"])
        self.assertEqual(started, replayed)
        self.assertEqual([self.session], self.enabled)

        stopped = self.manager.stop(self.session, self.stop_command)
        replayed_stop = self.manager.stop(self.session, self.stop_command)
        self.assertEqual("stopped", stopped["state"])
        self.assertEqual(stopped, replayed_stop)
        self.assertEqual([self.session], self.disabled)

    def test_rejects_stop_for_different_session(self):
        self.manager.start(self.session, self.start_command)
        with self.assertRaises(AuditSessionConflict):
            self.manager.stop(str(uuid.uuid4()), self.stop_command)
        self.assertEqual("running", self.manager.status()["state"])

    def test_failed_enable_leaves_recoverable_start_intent(self):
        def fail(_session):
            raise OSError("audit rules unavailable")
        manager = AuditSessionManager(self.path, fail, lambda _session: None)
        with self.assertRaises(OSError):
            manager.start(self.session, self.start_command)
        self.assertEqual("starting", manager.status()["state"])

        recovered = AuditSessionManager(
            self.path, lambda session: self.enabled.append(session),
            lambda session: self.disabled.append(session),
        )
        self.assertEqual("running", recovered.reconcile()["state"])
        self.assertEqual([self.session], self.enabled)

    def test_failed_disable_leaves_recoverable_stop_intent(self):
        self.manager.start(self.session, self.start_command)
        manager = AuditSessionManager(
            self.path, lambda _session: None,
            lambda _session: (_ for _ in ()).throw(OSError("disable failed")),
        )
        with self.assertRaises(OSError):
            manager.stop(self.session, self.stop_command)
        self.assertEqual("stopping", manager.status()["state"])

        recovered = AuditSessionManager(
            self.path, lambda _session: None,
            lambda session: self.disabled.append(session),
        )
        self.assertEqual("stopped", recovered.reconcile()["state"])
        self.assertEqual([self.session], self.disabled)

    def test_rejects_corrupt_state_instead_of_resetting(self):
        self.path.write_text("{broken", encoding="utf-8")
        with self.assertRaises(RuntimeError):
            AuditSessionManager(self.path, lambda _s: None, lambda _s: None)

    def test_state_file_is_private(self):
        self.manager.start(self.session, self.start_command)
        self.assertEqual(0o600, self.path.stat().st_mode & 0o777)


if __name__ == "__main__":
    unittest.main()
