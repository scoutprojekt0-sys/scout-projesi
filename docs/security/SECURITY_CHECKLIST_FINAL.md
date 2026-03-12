# Security Checklist Final

Status legend:
- `[x]` completed
- `[ ]` pending

## Auth and Session

- [x] Login brute-force lock and retry window
- [x] Token rotation (`/api/auth/refresh`) and old token invalidation
- [x] Session listing and selective revocation
- [x] Revoke-all-except-current flow
- [x] Password reset request + reset confirmation flow
- [x] Legacy wildcard token revocation command

## Authorization Boundaries

- [x] Opportunity create/update/delete limited to owner team
- [x] Application status change limited to owning team
- [x] Contact status change limited to recipient
- [x] Media delete limited to media owner
- [x] Ability middleware enforced on protected routes

## Abuse and Input Controls

- [x] Auth route rate limits
- [x] API read/write rate limits tunable by config
- [x] Validation rules on core write endpoints
- [x] Generic error messages for sensitive auth cases

## Observability and Incident Readiness

- [x] Security log channel with retention controls
- [x] Ops request metrics log channel
- [x] Slow request threshold alert-style logging
- [x] Request correlation id (`X-Request-Id`)

## Operational Safety

- [x] Backup/restore runbook documented
- [x] Rollback runbook documented
- [x] Monitoring/alerts runbook documented

## Evidence Links

- `tests/Feature/AuthSecurityHardeningTest.php`
- `tests/Feature/AuthPasswordResetTest.php`
- `tests/Feature/SecurityPentestScenarioTest.php`
- `docs/runbooks/monitoring-alerts.md`
- `docs/runbooks/backup-restore.md`
- `docs/runbooks/rollback-procedure.md`
