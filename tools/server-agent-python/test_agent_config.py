import json
import os
import tempfile
import unittest
from pathlib import Path

from agent_config import load_config


class AgentConfigTests(unittest.TestCase):
    def _write(self, directory, values, mode=0o600):
        path = Path(directory) / "agent.json"
        path.write_text(json.dumps(values), encoding="utf-8")
        os.chmod(path, mode)
        return path

    def _valid(self, directory):
        return {
            "central_url": "https://monitor.example.com",
            "agent_id": "agent-123",
            "token": "secret-token-value",
            "session_id": "session-uuid",
            "data_dir": str(Path(directory) / "state"),
            "audit_log_path": str(Path(directory) / "audit.log"),
        }

    def test_loads_valid_private_config(self):
        with tempfile.TemporaryDirectory() as tmp:
            config = load_config(self._write(tmp, self._valid(tmp)))
            self.assertEqual("https://monitor.example.com", config.central_url)
            self.assertEqual(15.0, config.interval_seconds)
            self.assertEqual(100_000, config.max_spool_events)

    def test_rejects_group_or_world_accessible_config(self):
        with tempfile.TemporaryDirectory() as tmp:
            with self.assertRaises(PermissionError):
                load_config(self._write(tmp, self._valid(tmp), 0o640))

    def test_rejects_http_and_embedded_credentials(self):
        for url in ("http://monitor.example.com", "https://user:pass@monitor.example.com"):
            with self.subTest(url=url), tempfile.TemporaryDirectory() as tmp:
                values = self._valid(tmp)
                values["central_url"] = url
                with self.assertRaises(ValueError):
                    load_config(self._write(tmp, values))

    def test_rejects_unknown_or_missing_keys(self):
        with tempfile.TemporaryDirectory() as tmp:
            values = self._valid(tmp)
            values["debug"] = True
            with self.assertRaises(ValueError):
                load_config(self._write(tmp, values))

    def test_rejects_unsafe_intervals(self):
        with tempfile.TemporaryDirectory() as tmp:
            values = self._valid(tmp)
            values["interval_seconds"] = 0
            with self.assertRaises(ValueError):
                load_config(self._write(tmp, values))


if __name__ == "__main__":
    unittest.main()
