#!/usr/bin/env bash
set -euo pipefail

required_vars=(
  RAILWAY_TOKEN
  RAILWAY_PROJECT_ID
  RAILWAY_ENVIRONMENT_ID
  RAILWAY_SERVICE_ID
  STAGING_HEALTHCHECK_URL
)

for var_name in "${required_vars[@]}"; do
  if [[ -z "${!var_name:-}" ]]; then
    echo "Missing required env var: ${var_name}" >&2
    exit 1
  fi
done

echo "Installing Railway CLI..."
npm install --global @railway/cli

echo "Linking Railway project/environment/service..."
railway link \
  --project "${RAILWAY_PROJECT_ID}" \
  --environment "${RAILWAY_ENVIRONMENT_ID}" \
  --service "${RAILWAY_SERVICE_ID}"

echo "Deploying to staging..."
railway up --detach --service "${RAILWAY_SERVICE_ID}"

echo "Running production migrations on staging..."
railway run --service "${RAILWAY_SERVICE_ID}" php artisan migrate --force

echo "Warming config cache..."
railway run --service "${RAILWAY_SERVICE_ID}" php artisan config:cache

echo "Health-check: ${STAGING_HEALTHCHECK_URL}"
for i in {1..30}; do
  if curl -fsS "${STAGING_HEALTHCHECK_URL}" > /dev/null; then
    echo "Staging is healthy."
    exit 0
  fi
  sleep 2
done

echo "Staging health-check failed." >&2
exit 1
