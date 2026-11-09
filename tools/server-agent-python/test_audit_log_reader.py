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

    def test_rejects_invalid_batch_size(self):
        with self.assertRaises(ValueError):
            self.reader.read_batch(0)


if __name__ == "__main__":
    unittest.main()
