#!/usr/bin/env sh
set -e

ATTEMPT=0
MAX_ATTEMPT=15

until php artisan migrate --force; do
  ATTEMPT=$((ATTEMPT + 1))
  if [ "$ATTEMPT" -ge "$MAX_ATTEMPT" ]; then
    echo "Migration failed after $MAX_ATTEMPT attempts."
    exit 1
  fi
  echo "Database not ready, retrying migration in 3s... ($ATTEMPT/$MAX_ATTEMPT)"
  sleep 3
done

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
