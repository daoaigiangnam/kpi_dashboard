"""Safe polling loop for the Common Server Agent.

This stage performs heartbeat, command polling and durable command intake only.
It intentionally does not execute host operations or ACK a command as executing.
"""
from __future__ import annotations

import platform
import socket
from datetime import datetime, timezone
from typing import Any

from central_client import CentralClient
from command_journal import CommandJournal, TERMINAL_STATES
from event_spool import EventSpool
from auditd_collector import AuditdRawParser


class AgentWorker:
    def __init__(self, client: CentralClient, journal: CommandJournal,
                 agent_version: str = "0.1.0"):
        self.client = client
        self.journal = journal
        self.agent_version = agent_version

    @staticmethod
    def _not_expired(value: str | None) -> bool:
        if value is None:
            return True
        try:
            expires = datetime.fromisoformat(value.replace("Z", "+00:00"))
            if expires.tzinfo is None:
                return False
            return expires > datetime.now(timezone.utc)
        except (TypeError, ValueError):
            return False

    def ingest_auditd_raw(self, lines: list[str], session_id: str,
                          spool: EventSpool) -> int:
        """Parse complete auditd raw records and durably enqueue execution events."""
        events = AuditdRawParser().parse(lines, session_id)
        return sum(1 for event in events if spool.enqueue(session_id, event))

    def flush_events(self, spool: EventSpool, session_id: str,
                     batch_size: int = 100) -> int:
        """Upload one batch and delete it locally only after explicit Central success."""
        events = spool.peek_batch(session_id, batch_size)
        if not events:
            return 0
        response = self.client.upload_events({"session_id": session_id, "events": events})
        if response.get("ok") is not True:
            raise RuntimeError("Central did not confirm audit event batch")
        # Central uses insertOrIgnore; ok=true means every item was processed,
        # including duplicates already stored. It is therefore safe to clear
        # this exact batch, not merely the number newly inserted.
        return spool.acknowledge_batch(session_id, events)

    def run_once(self) -> dict[str, int]:
        heartbeat = self.client.heartbeat({
            "agent_version": self.agent_version,
            "hostname": socket.gethostname()[:190],
            "os_info": {
                "system": platform.system(),
                "release": platform.release(),
                "machine": platform.machine(),
                "python": platform.python_version(),
            },
            "metadata": {"transport": "https"},
        })
        if heartbeat.get("ok") is not True:
            raise RuntimeError("Central did not confirm heartbeat")

        response = self.client.poll_commands()
        commands = response.get("commands")
        if not isinstance(commands, list):
            raise ValueError("Central command response must contain a commands array")

        accepted = duplicates = expired = terminal_replays = 0
        for command in commands:
            if not isinstance(command, dict):
                raise ValueError("Central returned an invalid command object")
            if not self._not_expired(command.get("expires_at")):
                expired += 1
                continue

            row, is_new = self.journal.record_received(command)
            if is_new:
                accepted += 1
            else:
                duplicates += 1

            # Retry durable ACKs after a lost HTTP response. A received command
            # is deliberately not ACKed as executing until a real executor exists.
            if row["state"] in TERMINAL_STATES or row["state"] == "executing":
                payload: dict[str, Any] = {
                    "command_id": row["command_id"],
                    "status": row["state"],
                }
                if row["result_json"] is not None:
                    import json
                    payload["result"] = json.loads(row["result_json"])
                if row["error"] is not None:
                    payload["error"] = row["error"]
                self.client.acknowledge(payload)
                terminal_replays += 1

        return {
            "received": accepted,
            "duplicates": duplicates,
            "expired": expired,
            "terminal_ack_replays": terminal_replays,
        }
