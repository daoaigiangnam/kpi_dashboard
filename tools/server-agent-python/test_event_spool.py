import tempfile
import unittest
from pathlib import Path

from event_spool import EventSpool


class EventSpoolTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.spool = EventSpool(Path(self.tmp.name) / "spool.sqlite3", max_events=3)
        self.session = "session-1"

    def tearDown(self):
        self.spool.close()
        self.tmp.cleanup()

    def event(self, key):
        return {"event_key": key, "event_time": "2026-11-09T10:00:00Z",
                "executable": "/usr/bin/id"}

    def test_enqueue_and_duplicate_event_key(self):
        self.assertTrue(self.spool.enqueue(self.session, self.event("evt-1")))
        self.assertFalse(self.spool.enqueue(self.session, self.event("evt-1")))
        self.assertEqual(1, self.spool.pending_count(self.session))

    def test_peek_batch_does_not_delete_events(self):
        self.spool.enqueue(self.session, self.event("evt-1"))
        self.spool.enqueue(self.session, self.event("evt-2"))
        batch = self.spool.peek_batch(self.session, 10)
        self.assertEqual(2, len(batch))
        self.assertEqual(2, self.spool.pending_count(self.session))

    def test_acknowledgement_removes_only_confirmed_keys(self):
        self.spool.enqueue(self.session, self.event("evt-1"))
        self.spool.enqueue(self.session, self.event("evt-2"))
        removed = self.spool.acknowledge_batch(self.session, [self.event("evt-1")])
        self.assertEqual(1, removed)
        remaining = self.spool.peek_batch(self.session)
        self.assertEqual(["evt-2"], [x["event_key"] for x in remaining])

    def test_capacity_is_bounded(self):
        for i in range(3):
            self.spool.enqueue(self.session, self.event(f"evt-{i}"))
        with self.assertRaises(OverflowError):
            self.spool.enqueue(self.session, self.event("evt-overflow"))
        self.assertEqual(3, self.spool.pending_count())

    def test_spool_survives_reopen(self):
        self.spool.enqueue(self.session, self.event("evt-persist"))
        self.spool.close()
        self.spool = EventSpool(Path(self.tmp.name) / "spool.sqlite3")
        self.assertEqual("evt-persist", self.spool.peek_batch(self.session)[0]["event_key"])


    def test_pending_sessions_lists_only_sessions_with_unacknowledged_events(self):
        self.spool.enqueue("session-old", self.event("evt-old"))
        self.spool.enqueue("session-new", self.event("evt-new"))
        self.assertEqual(
            ["session-old", "session-new"],
            self.spool.pending_sessions(),
        )
        self.spool.acknowledge_batch("session-old", [self.event("evt-old")])
        self.assertEqual(["session-new"], self.spool.pending_sessions())

    def test_rejects_invalid_event_key(self):
        with self.assertRaises(ValueError):
            self.spool.enqueue(self.session, {"event_key": ""})


if __name__ == "__main__":
    unittest.main()
