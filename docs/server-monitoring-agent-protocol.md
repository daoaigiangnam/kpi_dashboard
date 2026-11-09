# Server Monitoring Phase 1 – Agent Protocol

## Scope

Phase 1 consists of a Central Laravel control plane and a Linux Common Server Agent. The Agent initiates outbound HTTPS requests only. No inbound listener or remote shell is exposed.

The only executable command types in Phase 1 are:

- `AUDIT_START`
- `AUDIT_STOP`

The Agent must reject every unknown command type. It must never execute a command string, shell fragment, or arbitrary executable supplied by Central.

## Agent identity and transport

Every server has a unique `agent_id` and a random one-time-issued token. Central stores only SHA-256(token). Requests use:

- `X-Agent-ID: <agent_id>`
- `Authorization: Bearer <token>`
- HTTPS with certificate verification enabled

The Agent must not accept a server identity from a request body. Central resolves the server from the authenticated Agent credentials. Tokens must be stored in a root-readable configuration file with mode 0600 and must never be logged.

## Central API

Base path: `/api/agent`

| Method | Path | Purpose |
|---|---|---|
| GET | `/config` | Fetch module flags and polling interval |
| GET | `/commands` | Fetch up to 10 queued commands |
| POST | `/heartbeat` | Report agent version, hostname and OS metadata |
| POST | `/ack` | Report command execution state |
| POST | `/audit/events` | Upload deduplicatable audit events |
| POST | `/audit/report` | Submit final session report |

All endpoints require Agent authentication. The current API rate limit is 120 requests/minute per route group.

### Command response

Each command contains:

- `command_id`: UUID
- `type`: `AUDIT_START` or `AUDIT_STOP`
- `payload.session_id`: UUID
- `expires_at`: ISO-8601 timestamp

The Agent must not start an expired command. It must ACK `executing` before performing the operation and ACK either `completed` or `failed` afterward.

### ACK state transitions

Central accepts these transitions:

- `delivered -> executing | completed | failed`
- `executing -> completed | failed`
- `executing -> executing` (idempotent retry; first ACK data is retained)
- `completed -> completed` and `failed -> failed` (idempotent terminal retry)
- A delayed `executing` retry received after `completed` or `failed` is acknowledged as stale and ignored. It must never move the command backwards or overwrite terminal data.

A terminal ACK is immutable: retries must not replace its result, error, or completion timestamp. An expired command is rejected if it has not entered `executing`; a command already executing may finish and report its terminal ACK after the start deadline.

### Durable Agent command journal and duplicate prevention

The Agent implementation must use a local SQLite journal (or an equivalently durable transactional store) keyed by `command_id`. An in-memory set is not sufficient.

Minimum journal fields:

- `command_id` (primary key), `type`, canonical payload/hash, `session_id`
- `state`: `received`, `executing`, `completed`, `failed`
- `received_at`, `started_at`, `finished_at`, `last_ack_at`
- `result_json`, `error`, `attempt_count`

Processing rules:

1. Acquire a process-wide lock before polling/dispatching so two workers cannot execute the same command concurrently.
2. Validate the command UUID, expiry, exact allowlisted type, and payload schema. Reject unknown types; never pass a Central-supplied string to a shell.
3. In one SQLite transaction, insert the command as `received`. If the ID already exists, compare type and canonical payload hash. A mismatch is a security/protocol error; never replace the stored command.
4. If the journal says `completed` or `failed`, do not run the operation again; resend the stored terminal ACK.
5. Persist `executing` and commit before performing any host operation. Then send the `executing` ACK. If the ACK request fails, continue using the journal and retry delivery; do not discard the command.
6. Implement `AUDIT_START` and `AUDIT_STOP` as idempotent desired-state reconciliation, not blind imperative shell scripts. START checks whether this Agent's uniquely tagged audit rules are already installed before adding them. STOP removes only rules owned by this Agent and treats an already-absent owned rule set as stopped.
7. Persist the terminal result before sending the terminal ACK. Retry ACK delivery from the journal until Central confirms it. A duplicate poll or lost HTTP response must not repeat host side effects.
8. On restart with a journal entry in `executing`, reconcile actual audit-rule state against the command's desired state, then persist the outcome. Do not assume the operation did not run merely because the terminal ACK was not recorded.

This provides durable deduplication and convergent recovery for the approved audit operations; it does not claim mathematically exactly-once execution across arbitrary host failures. Host-side reconciliation and explicit ownership markers are required to close the crash-after-side-effect window.

SQLite journal, database directory, audit-rule ownership metadata, and spool must be root-owned and inaccessible to unprivileged users. Use restrictive file modes, SQLite transactions, and a single-writer lock. Never log bearer tokens or sensitive command payloads.

## Audit session lifecycle

Expected sequence:

1. An authorized administrator requests START.
2. Central creates a `queued` session and an `AUDIT_START` command in one transaction.
3. Agent receives the command, ACKs `executing`, installs the approved audit rules, then ACKs `completed`.
4. Central changes the session to `running` and sets `audit_enabled=true`.
5. Agent reads and uploads audit events in batches. It retains unsent events in a durable local spool.
6. Administrator requests STOP. Central changes the session to `stopping` and queues `AUDIT_STOP`.
7. Agent removes only rules that it installed, flushes pending events, then ACKs STOP as completed or failed.
8. On successful STOP, Central clears `audit_enabled`. Agent submits the final report.

A failed STOP or failed final report does **not** prove that local collection stopped. Central therefore retains the last known enabled state. A new START must be blocked while that state is uncertain; an operator must investigate and reconcile the host.

Disabling an Agent in Central revokes API access. It does not remotely stop a local auditd rule or process and must not be represented as a successful audit stop.

## Audit event contract

Each event must contain:

- `event_key`: stable idempotency key unique within a server/session
- `event_time`: event timestamp
- Optional identity/process fields: `username`, `uid`, `auid`, `pid`, `ppid`, `executable`, `arguments`, `audit_serial`, `source_ip`
- Optional `raw_event`: structured source records for later verification

Central deduplicates on `(server_id, session_id, event_key)`. The Central database, not an Agent-supplied count, is the source of truth for accepted event totals.

An execve audit event proves that an execution syscall was observed; it does not by itself prove that the command completed successfully or achieved its intended effect. Preserve syscall success/exit information in the raw event where available.

## Audit scope and privacy

The intended collection scope is process execution via Linux auditd (`execve`/`execveat`). It is not keylogging and must not collect all keyboard input, passwords, terminal contents, or shell history. Shell built-ins that do not create an execution syscall (for example `cd`) are outside this scope.

The Agent must not broaden audit rules beyond the approved command-execution scope without a separately reviewed change.

## Agent reliability requirements

Before production acceptance, the Agent must provide:

- Durable local event spool and retry after network interruption.
- Rotation-aware audit log reading and stable event identifiers.
- Safe handling of duplicate commands and duplicate event batches.
- Strict command-type allowlist; no shell interpolation.
- TLS certificate validation; no insecure TLS fallback.
- Root-owned config/token with restrictive permissions.
- Structured logs that never contain the bearer token.
- systemd service with restart policy and least-privilege review.
- Explicit audit rule ownership so STOP removes only rules installed by this Agent.
- Graceful handling of reboot, auditd restart, log rotation, disk-full and Central outage.

## Acceptance gates (not yet satisfied by this document)

- Python Agent implementation and parser tests.
- Agent installer and systemd unit tested on supported Ubuntu versions.
- Laravel feature tests for authentication, command transitions, expiration, event deduplication and session lifecycle.
- Migration up/down tested against a disposable copy of the application's actual schema.
- End-to-end Central ↔ Agent test on staging.
- Security review of token lifecycle, audit rules, local spool permissions and log retention.

This protocol document is a design contract. It is not evidence that the Agent, installer, tests or staging validation have been implemented or passed.
