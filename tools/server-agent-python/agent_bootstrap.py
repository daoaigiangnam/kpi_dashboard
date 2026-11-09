"""Construct the Common Server Agent runtime from validated local config.

Privileged auditd changes are reachable only through the allowlisted durable
command executor. Merely starting the service does not enable audit rules.
"""
from __future__ import annotations

import os
from pathlib import Path

from agent_config import AgentConfig
from agent_runtime import AgentRuntime
from agent_service import AgentService
from agent_worker import AgentWorker
from audit_command_executor import AuditCommandExecutor
from audit_log_reader import AuditLogReader
from audit_session import AuditSessionManager
from auditd_adapter import AuditdAdapter
from central_client import CentralClient
from command_journal import CommandJournal
from event_spool import EventSpool


def build_service(config: AgentConfig) -> tuple[AgentService, AgentRuntime]:
    data_dir = config.data_dir
    data_dir.mkdir(parents=True, exist_ok=True, mode=0o700)
    if data_dir.is_symlink() or not data_dir.is_dir():
        raise ValueError("Agent data_dir must be a real directory")
    os.chmod(data_dir, 0o700)

    journal = CommandJournal(data_dir / "commands.sqlite3")
    spool = EventSpool(data_dir / "events.sqlite3", max_events=config.max_spool_events)
    client = CentralClient(
        config.central_url, config.agent_id, config.token,
        timeout=config.request_timeout,
    )

    # The privileged adapter is constructed but does not touch the host until
    # a validated AUDIT_START/AUDIT_STOP command reaches the executor.
    adapter = AuditdAdapter()
    sessions = AuditSessionManager(
        data_dir / "audit-session.json",
        adapter.ensure_enabled,
        adapter.ensure_disabled,
    )
    # Recover only a previously persisted transition; a clean stopped state
    # remains stopped and does not activate auditd at service startup.
    sessions.reconcile()

    executor = AuditCommandExecutor(journal, sessions, client)
    worker = AgentWorker(client, journal, command_executor=executor)
    reader = AuditLogReader(config.audit_log_path, data_dir / "audit-reader.cursor.json")
    runtime = AgentRuntime(worker, reader, spool, config.session_id)

    # Runtime closes the worker's command journal and the event spool on shutdown.
    service = AgentService(
        runtime,
        data_dir / "agent.lock",
        interval_seconds=config.interval_seconds,
    )
    return service, runtime
