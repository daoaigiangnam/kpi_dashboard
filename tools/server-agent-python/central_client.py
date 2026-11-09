"""HTTPS transport for the Common Server Agent.

Uses the Python standard library so the initial Ubuntu package has no runtime
third-party dependency. TLS verification is always enabled.
"""
from __future__ import annotations

import json
import urllib.error
import urllib.request
from typing import Any
from urllib.parse import urljoin


class CentralApiError(RuntimeError):
    def __init__(self, status: int, message: str):
        super().__init__(f"Central API request failed (HTTP {status}): {message}")
        self.status = status


class CentralClient:
    def __init__(self, base_url: str, agent_id: str, token: str, timeout: float = 15.0):
        base = base_url.strip().rstrip("/") + "/"
        if not base.startswith("https://"):
            raise ValueError("Central URL must use HTTPS")
        if not agent_id or not token:
            raise ValueError("agent_id and token are required")
        if timeout <= 0:
            raise ValueError("timeout must be positive")
        self.base_url = base
        self.agent_id = agent_id
        self._token = token
        self.timeout = timeout

    def _request(self, method: str, path: str, body: dict[str, Any] | None = None) -> dict[str, Any]:
        url = urljoin(self.base_url, path.lstrip("/"))
        data = None if body is None else json.dumps(body, separators=(",", ":")).encode("utf-8")
        headers = {
            "Accept": "application/json",
            "X-Agent-ID": self.agent_id,
            "Authorization": f"Bearer {self._token}",
        }
        if data is not None:
            headers["Content-Type"] = "application/json"
        request = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with urllib.request.urlopen(request, timeout=self.timeout) as response:
                raw = response.read()
                if not raw:
                    return {}
                parsed = json.loads(raw.decode("utf-8"))
                if not isinstance(parsed, dict):
                    raise CentralApiError(response.status, "invalid JSON response shape")
                return parsed
        except urllib.error.HTTPError as exc:
            # Do not include response bodies or request headers: either may contain
            # sensitive server data. Never log or stringify the bearer token.
            raise CentralApiError(exc.code, "request rejected by Central") from None
        except urllib.error.URLError as exc:
            raise ConnectionError("Central is unreachable or TLS validation failed") from None
        except (UnicodeDecodeError, json.JSONDecodeError):
            raise CentralApiError(502, "Central returned invalid JSON") from None

    def get_config(self) -> dict[str, Any]:
        return self._request("GET", "/api/agent/config")

    def poll_commands(self) -> dict[str, Any]:
        return self._request("GET", "/api/agent/commands")

    def heartbeat(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/api/agent/heartbeat", payload)

    def acknowledge(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/api/agent/ack", payload)

    def upload_events(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/api/agent/audit/events", payload)

    def submit_report(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/api/agent/audit/report", payload)
