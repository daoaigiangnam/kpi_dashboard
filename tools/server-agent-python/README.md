# Common Server Agent – command journal foundation

This package is the first executable Agent Core building block. It provides a durable SQLite command journal only; it does not yet poll Central, install auditd rules, or execute host operations.

## Requirements
- Python 3.10+
- SQLite 3

## Run tests
From this directory:

```bash
python3 -m unittest -v
```

## Safety contract
- Only AUDIT_START and AUDIT_STOP are accepted.
- command_id is the immutable idempotency key.
- Reuse of an ID with a different type or payload is rejected.
- State is persisted before host execution can be dispatched.
- Terminal results are immutable and survive process restart.
- No shell command or arbitrary executable is accepted.

The service integration must add a single-writer process lock, expiry validation, audit-rule desired-state reconciliation, HTTPS transport, and ACK retry loop before this can be installed on servers.
