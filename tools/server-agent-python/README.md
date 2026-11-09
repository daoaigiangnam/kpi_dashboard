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


## Auditd rule manager

`auditd_rules.py` renders a deterministic desired-state rule file for process execution auditing (`execve` and `execveat`) on selected Linux architectures. The default excludes records with an unset login UID (`auid=-1`); include them only when daemon/service executions must be audited, as event volume can increase substantially. The manager only renders content; it does not write privileged files or reload auditd.

## Audit log reader

`audit_log_reader.py` reads complete newline-terminated records from the configured audit log and persists a cursor atomically. The caller must commit the returned cursor only after records have been durably accepted by the local spool. It fails closed if the file is truncated or its inode changes; it does not silently reset the cursor. Rotation recovery (draining the renamed inode and switching to the new file) is intentionally a separate step and must be implemented before unattended production use.


### Rename rotation recovery

The reader follows the checkpointed device/inode into a renamed sibling such as `audit.log.1`, drains complete records from that same inode, and refuses to switch while unread bytes remain. The caller must commit the drained cursor, call `complete_rotation()`, then commit the returned cursor before reading the new active file. Missing rotated files, truncation, or inode mismatches fail closed. Compressed rotations are not followed; configure audit log rotation to retain the uncompressed renamed file until the Agent drains it. The integration layer must also coordinate with auditd's reopen/rotation procedure and alert on a blocked rotation rather than deleting old logs.


## Reader → Parser → SQLite Spool pipeline

`AgentWorker.ingest_audit_log_batch(reader, spool, session_id)` now reads one bounded batch, parses complete audit records, inserts normalized execution events into SQLite, and commits the reader cursor only after spool writes succeed. If parsing or a spool write fails, the cursor remains unchanged; replayed records are safe because the spool deduplicates by `(session_id, event_key)`.

For rotation, continue reading the checkpointed inode until drained, commit the final batch cursor, call `complete_audit_log_rotation(reader, drained_cursor)`, and commit the returned cursor before processing the new active file. The service loop must serialize these operations with a single-writer lock. This pipeline is a library integration; it does not yet open privileged audit logs or run as a systemd service.


## Agent service supervisor

`agent_supervisor.py` provides the process-lifecycle foundation for a long-running Linux Agent. It acquires a non-blocking `flock` on a dedicated lock file, records the owning PID for diagnostics, runs one supplied service cycle at a time, waits between normal cycles, and applies bounded exponential backoff after transient cycle failures. The lock is released in a `finally` block, including when the service exits unexpectedly.

The supervisor does not itself install signal handlers, open privileged audit logs, start/stop audit sessions, or execute queued commands. The future systemd entry point must translate SIGTERM/SIGINT into `request_stop()`, provide a cycle that safely coordinates heartbeat, audit ingestion and spool upload, and configure systemd restart policy. Do not run multiple independent readers for the same audit log/cursor.


## Signal-aware service entry point

`agent_service.py` wires `AgentWorker.run_once()` into `AgentSupervisor` and maps SIGTERM/SIGINT to a cooperative stop request. The signal handler only sets the supervisor stop event; it does not perform network calls, file I/O, or privileged cleanup. Previous process signal handlers are restored when the service exits.

This is the service lifecycle entry-point foundation, not yet a complete installable daemon. A deployment CLI still needs validated configuration loading, secret-file permissions, construction of the Central client/journal/spool, audit session and reader lifecycle, and systemd unit packaging. Do not start a privileged audit reader until its ownership, rotation coordination, and failure-alerting behavior are reviewed.


## Composed Agent runtime cycle

`agent_runtime.py` composes the existing worker and durable audit components into one supervised cycle: Central heartbeat/command intake, bounded audit-log read, parse + durable SQLite spool, HTTPS event upload, and pending-count reporting. It deliberately requires an explicit active `session_id`; it does not guess a session or enable audit collection itself. The runtime can be passed to `AgentService` as the worker-like object because it exposes `run_once()`.

The caller must construct the Central client, command journal, audit reader and spool from validated configuration, obtain/validate the active audit session, then construct `AgentRuntime`. After `AgentService.run()` returns, the bootstrap must call `runtime.close()` in its own `finally` block. A Central failure or spool failure raises to the Supervisor; reader cursor commit remains after durable spool only, and spool deletion remains after Central confirmation.

Rotation transition, audit session start/stop orchestration, privileged auditd rule ownership, config reload, installer and systemd packaging remain separate deployment work. This runtime is not yet a production-ready Ubuntu daemon.


## Agent configuration and bootstrap

`agent_config.py` loads a strict JSON configuration and rejects symlink/non-regular config files, permissions broader than 0600, unknown keys, non-HTTPS Central URLs, embedded URL credentials, and unsafe timing/spool limits. Keep the token only in this root/service-owned file; never put it in command-line arguments or logs.

Example configuration (replace all example values deliberately):

```json
{
  "central_url": "https://monitor.example.com",
  "agent_id": "ISSUED_AGENT_ID",
  "token": "ISSUED_AGENT_TOKEN",
  "session_id": "ACTIVE_AUDIT_SESSION_UUID",
  "data_dir": "/var/lib/server-monitoring-agent",
  "audit_log_path": "/var/log/audit/audit.log",
  "interval_seconds": 15,
  "request_timeout": 15,
  "max_spool_events": 100000
}
```

Set the file owner to the dedicated Agent service account (or root if the service runs as root) and mode 0600. The bootstrap creates a dedicated data directory with mode 0700 and constructs the HTTPS client, command journal, event spool, checkpointed audit reader, runtime and supervisor. It does not install auditd rules, grant capabilities, start an audit session, or create a systemd unit. The configured session ID must already be valid and active in Central; do not invent one. This is a construction/bootstrap layer, not yet an installable or production-ready daemon.


## Audit session lifecycle state machine

`audit_session.py` adds a crash-recoverable local START/STOP state machine with durable `stopped → starting → running → stopping → stopped` transitions. It writes transition intent before invoking injected desired-state callbacks, uses atomic fsync-backed state replacement with mode 0600, and can replay an interrupted transition after restart through `reconcile()`. Repeated START for the same running session and repeated STOP while stopped are idempotent; STOP for a different active session is rejected.

The callbacks `ensure_enabled(session_id)` and `ensure_disabled(session_id)` must themselves be idempotent and must implement desired state rather than toggle state. This module deliberately does not invoke shell commands, write auditd rules, or enable/disable kernel auditing. A reviewed privileged adapter and Central command/ACK integration are still required before this manager is connected to the live Agent Runtime. The current Bootstrap still accepts a configured session ID; dynamic session acquisition is not yet implemented.


## Durable audit command execution

`audit_command_executor.py` coordinates the local command journal, audit session state machine, and Central ACKs for the allowlisted `AUDIT_START` and `AUDIT_STOP` command types. It records the command before processing, sends the `executing` ACK before applying the desired state, persists the terminal result before its terminal ACK, and retries terminal ACKs without repeating the host-side effect. A process restart can replay an `executing` command; the session manager's durable state and idempotent desired-state callbacks are the recovery boundary.

`AgentWorker` accepts an optional command executor. Bootstrap intentionally does not supply one yet: no privileged auditd adapter is approved or connected. Until that adapter exists, the default worker continues durable command intake without executing host operations. This separation prevents a test callback or no-op implementation from being mistaken for real audit enforcement.


## Privileged auditd adapter boundary

`auditd_adapter.py` is the narrowly scoped privileged adapter for the dedicated `/etc/audit/rules.d/50-server-monitoring.rules` file. It uses fixed executable paths and argument arrays with `shell=False`, bounded timeouts, an atomic mode-0640 file replacement, `augenrules --load`, and `auditctl -l` verification. It refuses symlink/non-regular managed paths and refuses changes when the kernel audit subsystem is immutable (`enabled 2`). Disable removes only the dedicated managed file and verifies that the configured managed key is no longer loaded; it does not flush unrelated audit rules or stop the audit daemon.

This adapter is not connected to Bootstrap or the live service yet. It must be run only by a reviewed privileged service account with ownership of the dedicated rules directory. Ubuntu package-specific binary paths, auditd configuration, rule ordering, immutable mode, and interaction with pre-existing local rules must be verified on a disposable Ubuntu staging VM before activation. The adapter changes process-execution rules only; it does not collect keystrokes or prove command success.
