"""Strict JSON configuration loading for the Common Server Agent."""
from __future__ import annotations

import json
import os
import stat
from dataclasses import dataclass
from pathlib import Path
from urllib.parse import urlparse


@dataclass(frozen=True)
class AgentConfig:
    central_url: str
    agent_id: str
    token: str
    session_id: str
    data_dir: Path
    audit_log_path: Path
    interval_seconds: float = 15.0
    request_timeout: float = 15.0
    max_spool_events: int = 100_000


def load_config(path: str | Path) -> AgentConfig:
    config_path = Path(path)
    if config_path.is_symlink():
        raise ValueError("configuration file must not be a symlink")
    info = config_path.stat()
    if not stat.S_ISREG(info.st_mode):
        raise ValueError("configuration path must be a regular file")
    if stat.S_IMODE(info.st_mode) & 0o077:
        raise PermissionError("configuration file permissions must be 0600 or stricter")

    try:
        raw = json.loads(config_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeDecodeError, json.JSONDecodeError):
        raise ValueError("configuration file cannot be read as valid JSON") from None
    if not isinstance(raw, dict):
        raise ValueError("configuration root must be an object")

    required = {"central_url", "agent_id", "token", "session_id",
                "data_dir", "audit_log_path"}
    optional = {"interval_seconds", "request_timeout", "max_spool_events"}
    if required - raw.keys() or raw.keys() - required - optional:
        raise ValueError("configuration contains missing or unsupported keys")

    for key in ("central_url", "agent_id", "token", "session_id",
                "data_dir", "audit_log_path"):
        if not isinstance(raw[key], str) or not raw[key].strip():
            raise ValueError(f"{key} must be a non-empty string")

    parsed = urlparse(raw["central_url"])
    if parsed.scheme != "https" or not parsed.netloc or parsed.username or parsed.password:
        raise ValueError("central_url must be an HTTPS origin without embedded credentials")
    if any(ch.isspace() for ch in raw["agent_id"] + raw["token"]):
        raise ValueError("agent_id and token must not contain whitespace")

    try:
        interval = float(raw.get("interval_seconds", 15.0))
        timeout = float(raw.get("request_timeout", 15.0))
        max_events = int(raw.get("max_spool_events", 100_000))
    except (TypeError, ValueError):
        raise ValueError("numeric configuration values are invalid") from None
    if not 1 <= interval <= 3600 or not 1 <= timeout <= 120:
        raise ValueError("interval_seconds or request_timeout is outside safe bounds")
    if not 100 <= max_events <= 1_000_000:
        raise ValueError("max_spool_events is outside safe bounds")

    return AgentConfig(
        central_url=raw["central_url"].rstrip("/"),
        agent_id=raw["agent_id"],
        token=raw["token"],
        session_id=raw["session_id"],
        data_dir=Path(raw["data_dir"]),
        audit_log_path=Path(raw["audit_log_path"]),
        interval_seconds=interval,
        request_timeout=timeout,
        max_spool_events=max_events,
    )
