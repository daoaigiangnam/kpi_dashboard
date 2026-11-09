import io
import json
import unittest
import urllib.error
from unittest.mock import patch

from central_client import CentralApiError, CentralClient


class FakeResponse:
    status = 200

    def __init__(self, body):
        self.body = body

    def __enter__(self):
        return self

    def __exit__(self, *args):
        return False

    def read(self):
        return self.body


class CentralClientTests(unittest.TestCase):
    def setUp(self):
        self.client = CentralClient("https://central.example.test/", "agent-123", "secret-token")

    def test_rejects_non_https_endpoint(self):
        with self.assertRaises(ValueError):
            CentralClient("http://central.example.test", "a", "t")

    @patch("central_client.urllib.request.urlopen")
    def test_auth_headers_and_config_path(self, mocked):
        mocked.return_value = FakeResponse(b'{"config_version":1}')
        result = self.client.get_config()
        self.assertEqual(1, result["config_version"])
        req = mocked.call_args.args[0]
        self.assertEqual("GET", req.method)
        self.assertEqual("https://central.example.test/api/agent/config", req.full_url)
        self.assertEqual("agent-123", req.get_header("X-agent-id"))
        self.assertEqual("Bearer secret-token", req.get_header("Authorization"))
        self.assertEqual(15.0, mocked.call_args.kwargs["timeout"])

    @patch("central_client.urllib.request.urlopen")
    def test_post_serializes_json(self, mocked):
        mocked.return_value = FakeResponse(b'{"ok":true}')
        self.client.acknowledge({"command_id": "abc", "status": "executing"})
        req = mocked.call_args.args[0]
        self.assertEqual("POST", req.method)
        self.assertEqual("application/json", req.get_header("Content-type"))
        self.assertEqual(
            {"command_id": "abc", "status": "executing"},
            json.loads(req.data.decode()),
        )

    @patch("central_client.urllib.request.urlopen")
    def test_http_error_does_not_expose_response_body_or_token(self, mocked):
        mocked.side_effect = urllib.error.HTTPError(
            "https://central.example.test/api/agent/ack", 401, "Unauthorized",
            {}, io.BytesIO(b"secret-token leaked body"),
        )
        with self.assertRaises(CentralApiError) as caught:
            self.client.acknowledge({"status": "executing"})
        self.assertEqual(401, caught.exception.status)
        self.assertNotIn("secret-token", str(caught.exception))
        self.assertNotIn("leaked body", str(caught.exception))

    @patch("central_client.urllib.request.urlopen")
    def test_network_error_is_sanitized(self, mocked):
        mocked.side_effect = urllib.error.URLError("TLS certificate details")
        with self.assertRaises(ConnectionError) as caught:
            self.client.poll_commands()
        self.assertNotIn("TLS certificate details", str(caught.exception))


if __name__ == "__main__":
    unittest.main()
