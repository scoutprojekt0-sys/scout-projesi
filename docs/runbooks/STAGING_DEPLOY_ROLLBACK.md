# Staging Deploy and Rollback (Railway)

## Required GitHub Environment

Create `staging` environment in GitHub and add these secrets:

- `RAILWAY_TOKEN`
- `RAILWAY_PROJECT_ID`
- `RAILWAY_ENVIRONMENT_ID`
- `RAILWAY_SERVICE_ID`
- `STAGING_HEALTHCHECK_URL` (example: `https://<staging-domain>/up`)

## Deploy Flow

Workflow: `.github/workflows/deploy-staging.yml`

Triggers:
- push to `staging`
- manual `workflow_dispatch` with `ref`

Pipeline steps:
1. Run backend tests.
2. Deploy selected ref to Railway staging.
3. Run migrations.
4. Validate health-check endpoint.

## Rollback Flow

Workflow: `.github/workflows/rollback-staging.yml`

Trigger:
- manual `workflow_dispatch` with `rollback_ref`

Rollback approach:
- Re-deploy any previous commit/tag/branch to staging.
- Run migrations and health-check again.

## Recommended Rollback Input

- Last known good tag, for example: `v1.2.3`
- Or exact commit SHA, for example: `a1b2c3d4`
