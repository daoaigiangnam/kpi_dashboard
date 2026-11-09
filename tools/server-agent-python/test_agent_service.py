import signal
import tempfile
import threading
import unittest
from pathlib import Path
from unittest.mock import Mock, patch

from agent_service import AgentService


class AgentServiceTests(unittest.TestCase):
    def test_service_uses_worker_run_once_as_supervisor_cycle(self):
        with tempfile.TemporaryDirectory() as tmp:
            worker = Mock()
            service = AgentService(worker, Path(tmp) / "agent.lock", interval_seconds=1)
            service.supervisor.run = Mock(return_value={
                "attempted": 1, "successful": 1, "failed": 0
            })
            with patch("agent_service.signal.getsignal", return_value=signal.SIG_DFL), \
                 patch("agent_service.signal.signal"):
                result = service.run()

            self.assertEqual({"attempted": 1, "successful": 1, "failed": 0}, result)
            self.assertIs(service.supervisor.cycle, worker.run_once)

    def test_sigterm_and_sigint_request_cooperative_stop(self):
        with tempfile.TemporaryDirectory() as tmp:
            service = AgentService(Mock(), Path(tmp) / "agent.lock")
            with patch("agent_service.signal.getsignal", return_value=signal.SIG_DFL), \
                 patch("agent_service.signal.signal") as signal_set:
                service.install_signal_handlers()
                handler = signal_set.call_args_list[0].args[1]
                handler(signal.SIGTERM, None)
                self.assertTrue(service.supervisor.stop_event.is_set())
                service.restore_signal_handlers()

            registered = [call.args[0] for call in signal_set.call_args_list[:2]]
            self.assertEqual([signal.SIGTERM, signal.SIGINT], registered)


    def test_service_closes_runtime_after_supervisor_exits(self):
        with tempfile.TemporaryDirectory() as tmp:
            worker = Mock()
            service = AgentService(worker, Path(tmp) / "agent.lock")
            service.supervisor.run = Mock(return_value={
                "attempted": 1, "successful": 1, "failed": 0
            })
            with patch("agent_service.signal.getsignal", return_value=signal.SIG_DFL), \\
                 patch("agent_service.signal.signal"):
                service.run()
            worker.close.assert_called_once_with()

    def test_service_restores_signals_even_if_runtime_close_fails(self):
        with tempfile.TemporaryDirectory() as tmp:
            worker = Mock()
            worker.close.side_effect = RuntimeError("close failed")
            service = AgentService(worker, Path(tmp) / "agent.lock")
            service.supervisor.run = Mock(return_value={
                "attempted": 1, "successful": 1, "failed": 0
            })
            with patch("agent_service.signal.getsignal", return_value=signal.SIG_DFL), \\
                 patch("agent_service.signal.signal") as signal_set:
                with self.assertRaises(RuntimeError):
                    service.run()
            restored = [call.args[0] for call in signal_set.call_args_list[-2:]]
            self.assertEqual([signal.SIGTERM, signal.SIGINT], restored)

    def test_signal_handlers_require_main_thread(self):
        with tempfile.TemporaryDirectory() as tmp:
            service = AgentService(Mock(), Path(tmp) / "agent.lock")
            errors = []
            def install():
                try:
                    service.install_signal_handlers()
                except Exception as exc:
                    errors.append(exc)
            thread = threading.Thread(target=install)
            thread.start()
            thread.join()
            self.assertEqual(1, len(errors))
            self.assertIsInstance(errors[0], RuntimeError)


if __name__ == "__main__":
    unittest.main()
