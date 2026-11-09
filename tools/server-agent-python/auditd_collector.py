"""Parser for Linux auditd raw event records.

The collector accepts raw text supplied by a trusted reader (for example,
ausearch --format raw). It does not invoke subprocesses or modify audit rules.
"""
from __future__ import annotations

import re
import shlex
from dataclasses import dataclass, field
from datetime import datetime, timezone
from typing import Iterable


_EVENT_RE = re.compile(r"msg=audit\((?P<timestamp>\d+(?:\.\d+)?):(?P<serial>\d+)\)")
_HEX_RE = re.compile(r"^[0-9a-fA-F]+$")


def _fields(line: str) -> dict[str, str]:
    result: dict[str, str] = {}
    try:
        tokens = shlex.split(line, posix=True)
    except ValueError:
        tokens = line.split()
    for token in tokens:
        if "=" not in token:
            continue
        key, value = token.split("=", 1)
        if key and key not in result:
            result[key] = value
    return result


def _decode_proctitle(value: str | None) -> list[str]:
    if not value or not _HEX_RE.fullmatch(value) or len(value) % 2:
        return []
    try:
        raw = bytes.fromhex(value)
        return [part.decode("utf-8", errors="replace") for part in raw.split(b"\x00") if part]
    except ValueError:
        return []


@dataclass
class _AuditEvent:
    timestamp: str
    serial: str
    records: list[tuple[str, dict[str, str]]] = field(default_factory=list)

    def to_event(self, session_id: str) -> dict:
        syscall = next((fields for kind, fields in self.records if kind == "SYSCALL"), {})
        execve = [fields for kind, fields in self.records if kind == "EXECVE"]
        proctitle = next((fields.get("proctitle") for kind, fields in self.records
                          if kind == "PROCTITLE"), None)

        args: dict[int, str] = {}
        for fields in execve:
            for key, value in fields.items():
                match = re.fullmatch(r"a(\d+)", key)
                if match:
                    args[int(match.group(1))] = value
        arguments = [args[index] for index in sorted(args)] if args else _decode_proctitle(proctitle)

        epoch = float(self.timestamp)
        event_time = datetime.fromtimestamp(epoch, timezone.utc).isoformat()
        executable = syscall.get("exe") or syscall.get("comm")
        event_key = f"audit:{self.serial}"

        return {
            "event_key": event_key,
            "event_time": event_time,
            "event_type": "process_execution",
            "session_id": session_id,
            "audit_serial": int(self.serial),
            "syscall": syscall.get("syscall"),
            "success": syscall.get("success"),
            "exit_code": syscall.get("exit"),
            "pid": syscall.get("pid"),
            "ppid": syscall.get("ppid"),
            "uid": syscall.get("uid"),
            "euid": syscall.get("euid"),
            "auid": syscall.get("auid"),
            "gid": syscall.get("gid"),
            "exe": executable,
            "comm": syscall.get("comm"),
            "terminal": syscall.get("tty"),
            "arguments": arguments,
        }


class AuditdRawParser:
    def parse(self, lines: Iterable[str], session_id: str) -> list[dict]:
        if not session_id or not isinstance(session_id, str):
            raise ValueError("session_id is required")
        grouped: dict[str, _AuditEvent] = {}
        for line in lines:
            match = _EVENT_RE.search(line)
            if not match:
                continue
            kind_match = re.search(r"^type=(\w+)", line)
            if not kind_match:
                continue
            kind = kind_match.group(1)
            if kind not in {"SYSCALL", "EXECVE", "PROCTITLE"}:
                continue
            serial = match.group("serial")
            event = grouped.setdefault(
                serial, _AuditEvent(match.group("timestamp"), serial)
            )
            event.records.append((kind, _fields(line)))

        output = []
        for serial in sorted(grouped, key=lambda item: int(item)):
            event = grouped[serial]
            if any(kind == "SYSCALL" for kind, _ in event.records):
                output.append(event.to_event(session_id))
        return output
