# Production Monitoring Setup

## Scope

This setup provides lightweight host-level monitoring for the live Hetzner server:

- Docker service status
- public HTTPS health checks
- local reverse-proxy health check
- root disk usage
- backup freshness

## Files

- `scripts/monitor_prod.sh`
- output log: `~/monitoring/scout_api/monitor.log`
- last status: `~/monitoring/scout_api/last_status.txt`

## Manual Run

```bash
cd ~/apps/scout_api
bash scripts/monitor_prod.sh
```

## Cron Example

Every 5 minutes:

```bash
*/5 * * * * cd /home/deploy/apps/scout_api && /usr/bin/bash scripts/monitor_prod.sh >> /home/deploy/monitoring/scout_api/cron.log 2>&1
```

## Checks

The script fails if any of these are true:

- one of `app`, `nginx`, `db`, `redis`, `queue`, `scheduler` is down
- `caddy` is down
- `https://nextscout.pro/up` is not `200`
- `https://api.nextscout.pro/up` is not `200`
- `http://127.0.0.1:8081/up` is not `200`
- root disk usage is `>= 90%`
- latest backup is older than `26 hours`

## Tuning

Optional environment variables:

- `MAX_DISK_PERCENT`
- `MAX_BACKUP_AGE_HOURS`
- `APP_DIR`
- `CADDY_DIR`
- `LOG_DIR`

Example:

```bash
MAX_DISK_PERCENT=85 MAX_BACKUP_AGE_HOURS=20 bash scripts/monitor_prod.sh
```
