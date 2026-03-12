# Monitoring and Alerts Runbook

## Scope

This runbook covers basic operational monitoring and alert responses for Scout API.

## Log Channels

- `security`:
  - auth/session security events
- `ops`:
  - request summary metrics
  - slow request and 5xx alert-style logs

## Key Log Fields

- `request_id`
- `method`
- `path`
- `status`
- `duration_ms`
- `user_id`
- `ip`

## Suggested Alert Conditions

1. `request.alert` with `status >= 500` repeated for 5+ events in 5 minutes.
2. `request.alert` with `duration_ms >= MONITOR_SLOW_REQUEST_MS` repeated for 20+ events in 10 minutes.
3. Security channel spikes:
   - login lock events (`auth_temporarily_locked`)
   - suspicious token/session failures

## First Response Checklist

1. Correlate by `request_id` and route path.
2. Check if errors are concentrated on one endpoint.
3. Confirm DB/storage health and recent deploy changes.
4. If active incident:
   - apply rollback plan if needed
   - communicate impact and ETA

## Useful Local Commands

- Tail ops log:
  - `Get-Content storage/logs/ops-*.log -Wait`
- Tail security log:
  - `Get-Content storage/logs/security-*.log -Wait`
