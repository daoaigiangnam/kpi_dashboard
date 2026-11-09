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


## Auditd event parsing

`auditd_collector.py` parses raw audit records supplied by a trusted reader. It groups SYSCALL, EXECVE and PROCTITLE records by Audit Serial and emits normalized process-execution events. Failed exec attempts are retained as events; an audit record is not interpreted as proof that a command succeeded.

`AgentWorker.ingest_auditd_raw()` parses records and inserts them into the durable SQLite event spool. Re-reading the same serial within a session is deduplicated. This stage does not execute `ausearch`, change audit rules, or read privileged log files; a separately hardened reader/service integration is still required.


## Auditd rule management

`auditd_rules.py` renders a deterministic desired-state rule file for process execution auditing (`execve` and `execveat`) on selected Linux architectures. The default excludes records with an unset login UID (`auid=-1)); set `include_unset_login_uid=True` only when daemon/service executions must also be audited, since this can substantially increase event volume.

The manager only renders content and identifies the target path `/etc/audit/rules.d/50-server-monitoring.rules`. It does not write privileged files, invoke `auditctl`, or reload auditd. Those actions belong to a separately reviewed installer with explicit privilege boundaries.
