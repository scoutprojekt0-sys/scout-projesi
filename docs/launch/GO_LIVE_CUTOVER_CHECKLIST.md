# Go-Live Cutover Checklist

Date: 2026-02-25
Owner: Engineering + QA + Product

## T-60 min

- [ ] Confirm RC commit hash and release notes.
- [ ] Confirm all required GitHub checks are green.
- [ ] Confirm rollback target commit is documented.
- [ ] Confirm backup snapshot timestamp and integrity.

## T-30 min

- [ ] Announce launch window to stakeholders.
- [ ] Freeze non-critical merges to `main`.
- [ ] Verify environment variables in production.
- [ ] Verify DB migration plan and expected duration.

## T-10 min

- [ ] Enable maintenance mode if required by release plan.
- [ ] Trigger deployment from approved RC.
- [ ] Run database migrations.
- [ ] Verify app health endpoint (`/up`).

## T+0 (Go-Live)

- [ ] Disable maintenance mode.
- [ ] Run smoke tests:
  - [ ] auth login + `GET /api/auth/me`
  - [ ] opportunities list
  - [ ] application flow
  - [ ] contacts inbox/sent
  - [ ] media upload/list/delete
- [ ] Confirm no critical alerts in first 10 minutes.

## T+30 min

- [ ] Review 5xx rate and p95 latency.
- [ ] Review auth/session anomalies.
- [ ] Review media pipeline errors.
- [ ] Publish first launch status update.

## T+24h

- [ ] Publish day-1 launch report.
- [ ] Confirm open incidents and owners.
- [ ] Prioritize hotfixes (if any).
- [ ] Keep elevated monitoring cadence for week-1.
