# Monitoring and Alerting Runbook

## Health Endpoints

- Liveness: `GET /health/live`
- Readiness: `GET /health/ready`
- Platform check: `GET /up` (Laravel built-in)

`/health/ready` validates:
- database connectivity
- default storage write/delete

## Logging Channels

`config/logging.php`:
- `ops` stack: `daily` + `alerts`
- `alerts`: Slack webhook channel

Recommended production/staging env values:

- `LOG_CHANNEL=ops`
- `LOG_OPS_STACK=daily,alerts`
- `ALERT_LOG_LEVEL=error`
- `ALERT_SLACK_WEBHOOK_URL=<slack-incoming-webhook>`

## Exception Alerting

Unhandled exceptions in `production` and `staging` are reported to `ops` channel in `bootstrap/app.php`.

## Uptime Monitoring Recommendation

Use your provider uptime checks:
- `https://<staging-domain>/health/ready`
- `https://<prod-domain>/health/ready`

Suggested alert policy:
- trigger after 2 consecutive failures
- repeat notification every 10 minutes while incident is open

## Incident First Response

1. Confirm failing endpoint (`/health/ready`).
2. Inspect latest app logs (daily log file and Slack alert payload).
3. Check DB/storage availability.
4. Roll back staging/prod if needed (use rollback workflow for staging).
