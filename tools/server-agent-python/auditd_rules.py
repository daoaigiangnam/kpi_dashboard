"""Safe desired-state builder for Linux auditd process-execution rules.

This module renders rules only. Installation/reload requires a separately
privileged installer and is intentionally not performed by the Agent process.
"""
from __future__ import annotations

import re
from dataclasses import dataclass


_KEY_RE = re.compile(r"^[A-Za-z0-9_.-]{1,30}$")


@dataclass(frozen=True)
class AuditdRuleConfig:
    key: str = "server_monitor_exec"
    include_unset_login_uid: bool = False
    architectures: tuple[str, ...] = ("b64", "b32")

    def __post_init__(self) -> None:
        if not _KEY_RE.fullmatch(self.key):
            raise ValueError("audit rule key must be 1-30 safe characters")
        if not self.architectures or any(arch not in {"b64", "b32"} for arch in self.architectures):
            raise ValueError("architectures must contain b64 and/or b32")


class AuditdRuleManager:
    """Render deterministic audit rules without applying privileged changes."""

    def render(self, config: AuditdRuleConfig | None = None) -> str:
        config = config or AuditdRuleConfig()
        lines = []
        for arch in config.architectures:
            lines.append(
                f"-a always,exit -F arch={arch} -S execve,execveat "
                f"-F key={config.key}"
                + ("" if config.include_unset_login_uid else " -F auid!=-1")
            )
        return "\n".join(lines) + "\n"

    def desired_path(self) -> str:
        return "/etc/audit/rules.d/50-server-monitoring.rules"
