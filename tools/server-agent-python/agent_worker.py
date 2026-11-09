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

            # Terminal entries are never re-executed. Re-send the durable
            # terminal ACK so Central can recover from a lost prior response.
            if row["state"] in TERMINAL_STATES:
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
