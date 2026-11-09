# Common Server Agent – command journal foundation

This package contains the first executable Agent Core building blocks: a durable SQLite command journal and an HTTPS Central API client. It does not yet install auditd rules or execute host operations.

## Requirements
- Python 3.10+
- SQLite 3

## Run tests
From this directory:

```bash
python3 -m unittest -v
```

## HTTPS transport\n\n`central_client.py` provides authenticated HTTPS methods for config, command polling, heartbeat, ACK, event upload and final report. It rejects non-HTTPS base URLs, uses Python's default TLS certificate verification, applies request timeouts, and sanitizes transport errors so bearer tokens and response bodies are not exposed.\n\n## Safety contract
- Only AUDIT_START and AUDIT_STOP are accepted.
- command_id is the immutable idempotency key.
- Reuse of an ID with a different type or payload is rejected.
- State is persisted before host execution can be dispatched.
- Terminal results are immutable and survive process restart.
- No shell command or arbitrary executable is accepted.

The service integration must add a single-writer process lock, expiry validation, audit-rule desired-state reconciliation, HTTPS transport, and ACK retry loop before this can be installed on servers.
