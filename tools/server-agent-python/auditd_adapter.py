"""Narrow privileged adapter for managed auditd process-execution rules.

Only fixed system binaries and the dedicated managed rule file are touched.
No command strings, shell, arbitrary paths, or Central-provided arguments execute.
"""
from __future__ import annotations

import os
import re
import stat
import subprocess
import tempfile
from pathlib import Path
from typing import Callable

from auditd_rules import AuditdRuleConfig, AuditdRuleManager


class AuditdAdapterError(RuntimeError):
    pass


class AuditdImmutableError(AuditdAdapterError):
    pass


class AuditdAdapter:
    def __init__(
        self,
        rule_path: str | Path = "/etc/audit/rules.d/50-server-monitoring.rules",
        *,
        runner: Callable = subprocess.run,
        auditctl: str = "/sbin/auditctl",
        augenrules: str = "/sbin/augenrules",
        timeout: int = 15,
        config: AuditdRuleConfig | None = None,
    ):
        self.rule_path = Path(rule_path)
        self.runner = runner
        self.auditctl = auditctl
        self.augenrules = augenrules
        self.timeout = timeout
        self.config = config or AuditdRuleConfig()
        self.manager = AuditdRuleManager()
        if not 1 <= timeout <= 60:
            raise ValueError("timeout must be between 1 and 60 seconds")
        if self.rule_path.name != "50-server-monitoring.rules":
            raise ValueError("adapter only manages its dedicated rule filename")

    def _run(self, argv: list[str]) -> str:
        try:
            result = self.runner(
                argv, shell=False, check=False, capture_output=True,
                text=True, timeout=self.timeout,
                env={"PATH": "/usr/sbin:/usr/bin:/sbin:/bin", "LANG": "C"},
            )
        except subprocess.TimeoutExpired:
            raise AuditdAdapterError("auditd operation timed out") from None
        except OSError:
            raise AuditdAdapterError("required auditd executable is unavailable") from None
        if result.returncode != 0:
            # Never forward raw stderr: it may contain host-specific details.
            raise AuditdAdapterError(f"auditd operation failed (exit {result.returncode})")
        return result.stdout or ""

    def status(self) -> dict[str, int]:
        output = self._run([self.auditctl, "-s"])
        match = re.search(r"(?m)^enabled\s+(\d+)\s*$", output)
        if not match:
            raise AuditdAdapterError("auditctl status output is invalid")
        enabled = int(match.group(1))
        if enabled not in {0, 1, 2}:
            raise AuditdAdapterError("audit subsystem returned an unknown enabled state")
        return {"enabled": enabled}

    def _assert_mutable(self) -> None:
        if self.status()["enabled"] == 2:
            raise AuditdImmutableError("audit rules are immutable until reboot")

    def _write_rules(self, content: str) -> None:
        parent = self.rule_path.parent
        if parent.is_symlink() or not parent.is_dir():
            raise AuditdAdapterError("managed audit rules directory is unsafe")
        if self.rule_path.is_symlink():
            raise AuditdAdapterError("managed audit rules file must not be a symlink")
        if self.rule_path.exists() and not stat.S_ISREG(self.rule_path.lstat().st_mode):
            raise AuditdAdapterError("managed audit rules path must be a regular file")

        fd, temp_name = tempfile.mkstemp(prefix=".server-monitoring-", dir=str(parent))
        try:
            os.fchmod(fd, 0o640)
            with os.fdopen(fd, "w", encoding="utf-8") as stream:
                stream.write(content)
                stream.flush()
                os.fsync(stream.fileno())
            os.replace(temp_name, self.rule_path)
            dir_fd = os.open(str(parent), os.O_DIRECTORY)
            try:
                os.fsync(dir_fd)
            finally:
                os.close(dir_fd)
        finally:
            if os.path.exists(temp_name):
                os.unlink(temp_name)

    def _load_and_list(self) -> str:
        self._run([self.augenrules, "--load"])
        return self._run([self.auditctl, "-l"])

    def ensure_enabled(self, session_id: str) -> None:
        # Session ID is intentionally not interpolated into kernel rules.
        if not isinstance(session_id, str) or not session_id:
            raise ValueError("session_id is required")
        self._assert_mutable()
        expected = self.manager.render(self.config).strip().splitlines()
        self._write_rules(self.manager.render(self.config))
        loaded = self._load_and_list().splitlines()
        missing = [rule for rule in expected if rule not in loaded]
        if missing:
            raise AuditdAdapterError("managed execution rules did not become active")

    def ensure_disabled(self, session_id: str) -> None:
        if not isinstance(session_id, str) or not session_id:
            raise ValueError("session_id is required")
        self._assert_mutable()
        if self.rule_path.is_symlink():
            raise AuditdAdapterError("managed audit rules file must not be a symlink")
        if self.rule_path.exists():
            if not stat.S_ISREG(self.rule_path.lstat().st_mode):
                raise AuditdAdapterError("managed audit rules path must be a regular file")
            self.rule_path.unlink()
        loaded = self._load_and_list().splitlines()
        if any(f"key={self.config.key}" in line for line in loaded):
            raise AuditdAdapterError("managed execution rules remain active after removal")
