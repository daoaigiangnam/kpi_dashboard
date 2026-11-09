"""Linux service entry-point helpers for the Common Server Agent.

The embedding package/CLI owns configuration and privileged resource creation.
This module wires the already-constructed worker to the supervisor and maps
systemd termination signals to a cooperative stop request.
"""
from __future__ import annotations

import signal
import threading
from pathlib import Path
from typing import Any

from agent_supervisor import AgentSupervisor


class AgentService:
    def __init__(
        self,
        worker: Any,
        lock_path: str | Path,
        interval_seconds: float = 15.0,
        retry_min_seconds: float = 2.0,
        retry_max_seconds: float = 60.0,
    ):
        self.worker = worker
        self.supervisor = AgentSupervisor(
            cycle=self.worker.run_once,
            lock_path=lock_path,
            interval_seconds=interval_seconds,
            retry_min_seconds=retry_min_seconds,
            retry_max_seconds=retry_max_seconds,
        )
        self._previous_handlers: dict[int, Any] = {}

    def _handle_signal(self, signum: int, frame: object) -> None:
        # Signal handlers must stay minimal: do not perform I/O or cleanup here.
        self.supervisor.request_stop()

    def install_signal_handlers(self) -> None:
        if threading.current_thread() is not threading.main_thread():
            raise RuntimeError("signal handlers must be installed from the main thread")
        for signum in (signal.SIGTERM, signal.SIGINT):
            self._previous_handlers[signum] = signal.getsignal(signum)
            signal.signal(signum, self._handle_signal)

    def restore_signal_handlers(self) -> None:
        if threading.current_thread() is not threading.main_thread():
            raise RuntimeError("signal handlers must be restored from the main thread")
        for signum, handler in self._previous_handlers.items():
            signal.signal(signum, handler)
        self._previous_handlers.clear()

    def run(self) -> dict[str, int]:
        self.install_signal_handlers()
        try:
            return self.supervisor.run()
        finally:
            try:
                close = getattr(self.worker, "close", None)
                if callable(close):
                    close()
            finally:
                self.restore_signal_handlers()
