"""Single-instance service supervisor for the Common Server Agent.

This module owns process lifecycle only. It does not execute commands, modify
audit rules, or decide which audit session should be active.
"""
from __future__ import annotations

import fcntl
import os
import threading
import time
from pathlib import Path
from typing import Callable


class AgentAlreadyRunning(RuntimeError):
    """Another Agent process already owns the service lock."""


class AgentSupervisor:
    def __init__(
        self,
        cycle: Callable[[], object],
        lock_path: str | Path,
        interval_seconds: float = 15.0,
        retry_min_seconds: float = 2.0,
        retry_max_seconds: float = 60.0,
        stop_event: threading.Event | None = None,
        wait_fn: Callable[[float], object] | None = None,
    ):
        if interval_seconds <= 0:
            raise ValueError("interval_seconds must be positive")
        if retry_min_seconds <= 0 or retry_max_seconds < retry_min_seconds:
            raise ValueError("invalid retry backoff bounds")
        self.cycle = cycle
        self.lock_path = Path(lock_path)
        self.interval_seconds = interval_seconds
        self.retry_min_seconds = retry_min_seconds
        self.retry_max_seconds = retry_max_seconds
        self.stop_event = stop_event or threading.Event()
        self.wait_fn = wait_fn or self.stop_event.wait
        self.successful_cycles = 0
        self.failed_cycles = 0

    def _acquire_lock(self) -> int:
        self.lock_path.parent.mkdir(parents=True, exist_ok=True)
        flags = os.O_CREAT | os.O_RDWR
        if hasattr(os, "O_CLOEXEC"):
            flags |= os.O_CLOEXEC
        if hasattr(os, "O_NOFOLLOW"):
            flags |= os.O_NOFOLLOW
        fd = os.open(str(self.lock_path), flags, 0o640)
        try:
            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            os.close(fd)
            raise AgentAlreadyRunning("another Agent process owns the service lock") from None
        os.ftruncate(fd, 0)
        os.write(fd, (str(os.getpid()) + "\n").encode("ascii"))
        os.fsync(fd)
        return fd

    @staticmethod
    def _release_lock(fd: int) -> None:
        try:
            fcntl.flock(fd, fcntl.LOCK_UN)
        finally:
            os.close(fd)

    def run(self, max_cycles: int | None = None) -> dict[str, int]:
        """Run cycles until stopped; max_cycles is intended for tests/diagnostics."""
        if max_cycles is not None and max_cycles < 0:
            raise ValueError("max_cycles cannot be negative")
        fd = self._acquire_lock()
        attempted = 0
        backoff = self.retry_min_seconds
        try:
            while not self.stop_event.is_set():
                if max_cycles is not None and attempted >= max_cycles:
                    break
                attempted += 1
                try:
                    self.cycle()
                except Exception:
                    self.failed_cycles += 1
                    if self.stop_event.is_set():
                        break
                    self.wait_fn(backoff)
                    backoff = min(backoff * 2, self.retry_max_seconds)
                else:
                    self.successful_cycles += 1
                    backoff = self.retry_min_seconds
                    if not self.stop_event.is_set():
                        self.wait_fn(self.interval_seconds)
            return {
                "attempted": attempted,
                "successful": self.successful_cycles,
                "failed": self.failed_cycles,
            }
        finally:
            self._release_lock(fd)

    def request_stop(self) -> None:
        self.stop_event.set()
