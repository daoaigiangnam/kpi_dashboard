"""Construct the Common Server Agent runtime from validated local config.

This bootstrap does not install auditd rules or start/stop audit sessions.
The session identifier and audit log path must be provisioned deliberately.
"""
from __future__ import annotations

import os
from pathlib import Path

from agent_config import AgentConfig
from agent_runtime import AgentRuntime
from agent_service import AgentService
from agent_worker import AgentWorker
from audit_log_reader import AuditLogReader
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
    worker = AgentWorker(client, journal)
    reader = AuditLogReader(config.audit_log_path, data_dir / "audit-reader.cursor.json")
    runtime = AgentRuntime(worker, reader, spool, config.session_id)

    # Runtime owns both SQLite connections and closes both on service shutdown.
    runtime._owned_journal = journal
    service = AgentService(
        runtime,
        data_dir / "agent.lock",
        interval_seconds=config.interval_seconds,
    )
    return service, runtime
