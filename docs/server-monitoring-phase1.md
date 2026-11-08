# Server Monitoring – Phase 1
Hybrid design: Laravel Central control plane; Python Common Agent planned for Ubuntu; Command Audit defaults OFF. Resource Monitor reserved for Phase 2.

## Central
- Run `php artisan migrate --force`.
- Serve Central only over HTTPS.
- Grant `server_monitoring.view`, `server_monitoring.manage`, `server_monitoring.audit` to trusted operator groups.
- Token is generated randomly and only its SHA-256 hash is stored.

## Agent API
All endpoints require Bearer token and X-Agent-ID:
- GET /api/agent/config
- GET /api/agent/commands
- POST /api/agent/heartbeat
- POST /api/agent/ack
- POST /api/agent/audit/events
- POST /api/agent/audit/report

## Important integration requirements
- Add routes in routes/api.php and routes/web.php or register separate route files in bootstrap/app.php.
- Register middleware alias agent.token in bootstrap/app.php.
- Implement and test Python agent package, systemd service and auditd parser before field deployment.
- Validate role permissions and blade layout on staging.
- Auditd events represent process execution metadata and do not prove command success.
- Local and Central copies are not immutable; hash chaining/signing is a future hardening task.
- This branch is an integration foundation, not production-certified.
