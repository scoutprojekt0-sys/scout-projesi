# Rollback Procedure

## Trigger Conditions

Use rollback when any of these happen after deployment:
- sustained 5xx spike
- auth/session critical regression
- data integrity risk

## Rollback Steps

1. Announce incident and freeze non-critical changes.
2. Roll back application to previous stable commit.
3. If required, restore DB/media from latest healthy backup.
4. Run smoke checks:
   - auth login + me
   - opportunities list
   - application flow
   - contacts/media core actions
5. Disable maintenance mode and monitor.

## Verification Checklist

- Error rate stabilized
- Core user journeys restored
- No pending destructive migrations
- Incident notes recorded

## Post-Rollback Actions

- Create incident summary
- Add regression test for root cause
- Re-plan fixed release candidate
