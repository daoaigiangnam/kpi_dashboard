import tempfile
import threading
import unittest
from pathlib import Path

from agent_supervisor import AgentAlreadyRunning, AgentSupervisor


class FakeWait:
    def __init__(self, stop_event=None):
        self.delays = []
        self.stop_event = stop_event

    def __call__(self, seconds):
        self.delays.append(seconds)
        if self.stop_event is not None and len(self.delays) >= 2:
            self.stop_event.set()


class AgentSupervisorTest(unittest.TestCase):
    def test_runs_cycles_and_uses_normal_interval(self):
        with tempfile.TemporaryDirectory() as tmp:
            calls = []
            wait = FakeWait()
            supervisor = AgentSupervisor(
                cycle=lambda: calls.append("cycle"),
                lock_path=Path(tmp) / "agent.lock",
                interval_seconds=12,
                wait_fn=wait,
            )
            result = supervisor.run(max_cycles=3)

            self.assertEqual(3, len(calls))
            self.assertEqual({"attempted": 3, "successful": 3, "failed": 0}, result)
            self.assertEqual([12, 12, 12], wait.delays)

    def test_cycle_failure_uses_bounded_exponential_backoff_and_recovers(self):
        with tempfile.TemporaryDirectory() as tmp:
            calls = []
            wait = FakeWait()
            def cycle():
                calls.append("cycle")
                if len(calls) < 3:
                    raise ConnectionError("central unavailable")

            supervisor = AgentSupervisor(
                cycle=cycle,
                lock_path=Path(tmp) / "agent.lock",
                retry_min_seconds=2,
                retry_max_seconds=5,
                wait_fn=wait,
            )
            result = supervisor.run(max_cycles=3)

            self.assertEqual({"attempted": 3, "successful": 1, "failed": 2}, result)
            self.assertEqual([2, 4, 12], wait.delays)

    def test_second_supervisor_cannot_acquire_same_lock(self):
        with tempfile.TemporaryDirectory() as tmp:
            lock = Path(tmp) / "agent.lock"
            first = AgentSupervisor(lambda: None, lock)
            fd = first._acquire_lock()
            try:
                second = AgentSupervisor(lambda: None, lock)
                with self.assertRaises(AgentAlreadyRunning):
                    second._acquire_lock()
            finally:
                first._release_lock(fd)

    def test_lock_is_released_after_cycle_exception_is_handled(self):
        with tempfile.TemporaryDirectory() as tmp:
            lock = Path(tmp) / "agent.lock"
            stop = threading.Event()
            def cycle():
                raise RuntimeError("transient")
            wait = lambda seconds: stop.set()
            first = AgentSupervisor(cycle, lock, stop_event=stop, wait_fn=wait)
            result = first.run()
            self.assertEqual(1, result["failed"])

            second = AgentSupervisor(lambda: None, lock)
            fd = second._acquire_lock()
            second._release_lock(fd)

    def test_rejects_invalid_intervals(self):
        with tempfile.TemporaryDirectory() as tmp:
            with self.assertRaises(ValueError):
                AgentSupervisor(lambda: None, Path(tmp) / "lock", interval_seconds=0)
            with self.assertRaises(ValueError):
                AgentSupervisor(lambda: None, Path(tmp) / "lock",
                                retry_min_seconds=5, retry_max_seconds=2)


if __name__ == "__main__":
    unittest.main()
