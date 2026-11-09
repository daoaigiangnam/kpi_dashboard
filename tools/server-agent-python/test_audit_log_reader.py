import tempfile
import unittest
from pathlib import Path

from audit_log_reader import AuditLogIdentityChanged, AuditLogReader


class AuditLogReaderTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        root = Path(self.tmp.name)
        self.log = root / "audit.log"
        self.cursor = root / "state" / "cursor.json"
        self.log.write_text('line one\npartial', encoding="utf-8")
        self.reader = AuditLogReader(self.log, self.cursor)

    def tearDown(self):
        self.tmp.cleanup()

    def test_reads_only_complete_lines_and_commits_cursor(self):
        lines, cursor = self.reader.read_batch()
        self.assertEqual(["line one"], lines)
        self.assertEqual(0, self.reader._load_cursor()["offset"])
        self.reader.commit(cursor)
        self.assertEqual(cursor["offset"], self.reader._load_cursor()["offset"])

    def test_uncommitted_batch_is_read_again_after_restart(self):
        first, cursor = self.reader.read_batch()
        second, _ = self.reader.read_batch()
        self.assertEqual(first, second)
        self.reader.commit(cursor)
        self.assertEqual([], self.reader.read_batch()[0])

    def test_appended_complete_line_is_read_after_partial_tail(self):
        lines, cursor = self.reader.read_batch()
        self.reader.commit(cursor)
        with self.log.open("a", encoding="utf-8") as stream:
            stream.write(" completed\n")
        lines, _ = self.reader.read_batch()
        self.assertEqual(["partial completed"], lines)

    def test_detects_truncation_without_silent_cursor_reset(self):
        lines, cursor = self.reader.read_batch()
        self.reader.commit(cursor)
        self.log.write_text("x\n", encoding="utf-8")
        with self.assertRaises(AuditLogIdentityChanged):
            self.reader.read_batch()


    def test_drains_renamed_inode_before_switching_to_new_active_log(self):
        _, cursor = self.reader.read_batch()
        self.reader.commit(cursor)
        rotated = self.log.with_name("audit.log.1")
        self.log.rename(rotated)
        self.log.write_text("new active line\\n", encoding="utf-8")

        old_lines, old_cursor = self.reader.read_batch()
        self.assertEqual(["partial"], old_lines)
        self.reader.commit(old_cursor)

        empty_lines, drained_cursor = self.reader.read_batch()
        self.assertEqual([], empty_lines)
        next_cursor = self.reader.complete_rotation(drained_cursor)
        self.reader.commit(next_cursor)

        active_lines, active_cursor = self.reader.read_batch()
        self.assertEqual(["new active line"], active_lines)
        self.reader.commit(active_cursor)

    def test_rotation_switch_refuses_to_skip_unread_rotated_bytes(self):
        _, cursor = self.reader.read_batch()
        self.reader.commit(cursor)
        rotated = self.log.with_name("audit.log.1")
        self.log.rename(rotated)
        self.log.write_text("new active\\n", encoding="utf-8")

        _, rotated_cursor = self.reader.read_batch()
        with rotated.open("a", encoding="utf-8") as stream:
            stream.write("late rotated record\\n")
        with self.assertRaises(AuditLogIdentityChanged):
            self.reader.complete_rotation(rotated_cursor)

    def test_missing_rotated_inode_fails_closed(self):
        _, cursor = self.reader.read_batch()
        self.reader.commit(cursor)
        rotated = self.log.with_name("audit.log.1")
        self.log.rename(rotated)
        self.log.write_text("new active\\n", encoding="utf-8")
        rotated.unlink()
        with self.assertRaises(AuditLogIdentityChanged):
            self.reader.read_batch()

    def test_rejects_invalid_batch_size(self):
        with self.assertRaises(ValueError):
            self.reader.read_batch(0)


if __name__ == "__main__":
    unittest.main()
