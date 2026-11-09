"""Durable dispatcher for allowlisted audit session commands.

The injected session manager owns idempotent desired-state transitions. This
executor never invokes a shell or performs privileged host operations directly.
"""
from __future__ import annotations

import json
from typing import Any

from audit_session import AuditSessionManager
from command_journal import CommandJournal, TERMINAL_STATES


class AuditCommandExecutor:
    def __init__(self, journal: CommandJournal, sessions: AuditSessionManager, client: Any):
        self.journal = journal
        self.sessions = sessions
        self.client = client

    def _ack(self, row: dict[str, Any]) -> None:
        payload: dict[str, Any] = {
            "command_id": row["command_id"],
            "status": row["state"],
        }
        if row.get("result_json") is not None:
            payload["result"] = json.loads(row["result_json"])
        if row.get("error") is not None:
            payload["error"] = row["error"]
        response = self.client.acknowledge(payload)
        if response.get("ok") is not True:
            raise RuntimeError("Central did not confirm command acknowledgement")

    def execute(self, command: dict[str, Any]) -> dict[str, Any]:
        """Persist, ACK executing, apply desired state, persist result, ACK terminal.

        If the process stops after the local effect but before terminal persistence,
        replaying the command re-applies an idempotent desired state and completes
        the journal. If a terminal ACK is lost, replay only resends that ACK.
        """
        row, _is_new = self.journal.record_received(command)
        if row["state"] in TERMINAL_STATES:
            self._ack(row)
            return row

        if row["state"] == "received":
            row = self.journal.mark_executing(row["command_id"])
        if row["state"] != "executing":
            raise RuntimeError("command journal returned an unsupported state")

        # Central must know execution has started before the host state changes.
        self._ack(row)

        payload = json.loads(row["payload_json"])
        session_id = payload["session_id"]
        try:
            if row["command_type"] == "AUDIT_START":
                effect = self.sessions.start(session_id, row["command_id"])
            elif row["command_type"] == "AUDIT_STOP":
                effect = self.sessions.stop(session_id, row["command_id"])
            else:
                raise ValueError("unsupported audit command")
            result = {
                "session_id": session_id,
                "session_state": effect["state"],
            }
            row = self.journal.finish(row["command_id"], "completed", result=result)
        except Exception as exc:
            # Do not leak arbitrary exception strings, paths or host details to Central.
            row = self.journal.finish(
                row["command_id"], "failed",
                error=f"{type(exc).__name__}: audit session transition failed",
            )
        self._ack(row)
        return row
