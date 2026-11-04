# FTTH / WAN Network Monitoring

## Configuration

Create or edit an Internet service under **IT Service Monitoring → Services**.

For `Service Type = INTERNET`, configure:

- `WAN IP / Monitor Target`: public WAN IP or valid hostname.
- `Check Method`: `PING` or `Check Port`.
- `Check Port`: required when method is `port`.
- `Check Interval`: 30s / 1m / 5m / 10m.
- `Timeout`: 3s / 5s / 10s.

## Check behavior

### PING

The monitoring server sends one ICMP echo request to the configured target. The result records online/offline, latency and packet loss.

### Check Port

The monitoring server opens a TCP connection to the configured target and port. A successful TCP connection is considered online.

This is a TCP reachability check, not an application protocol health check.

## Runtime

The Laravel scheduler runs `services:monitor-network` every minute. Each service has its own interval, so services that are not due are skipped.

Manual test:

```bash
php artisan services:monitor-network --limit=500
```

Check scheduler:

```bash
php artisan schedule:list
```

Production cron should run Laravel scheduler every minute:

```cron
* * * * * cd /path/to/kpi_dashboard && php artisan schedule:run >> /dev/null 2>&1
```

## Status

Each monitored service stores:

- `monitor_status`: `online` / `offline`
- `monitor_last_latency_ms`
- `monitor_packet_loss_percent`
- `monitor_failure_count`
- `monitor_last_checked_at`
- `monitor_down_since`

When a service goes offline, an open record is created in `service_monitor_events`. When it recovers, the incident is closed and its downtime duration is stored.

## Database migration

After deployment:

```bash
php artisan migrate --force
php artisan optimize:clear
```

The monitoring worker runs from the application server, so the application server must have network access to the configured WAN targets and ICMP/TCP egress must be permitted by its firewall.
