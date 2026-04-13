#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ "${APP_KEY:-}" = "" ] || [ "${APP_KEY:-}" = "base64:replace-with-generated-key" ]; then
  echo "APP_KEY is missing. Set a real APP_KEY in .env.production."
  exit 1
fi

if [ ! -L public/storage ]; then
  php artisan storage:link >/dev/null 2>&1 || true
fi

php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true

role="${CONTAINER_ROLE:-app}"

if [ "$role" = "app" ]; then
  until php artisan migrate --force; do
    echo "Waiting for database before running migrations..."
    sleep 5
  done
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

if [ "$role" = "queue" ]; then
  exec php artisan queue:work --verbose --tries=3 --timeout=120
fi

if [ "$role" = "scheduler" ]; then
  while true; do
    php artisan schedule:run --verbose --no-interaction
    sleep 60
  done
fi

exec "$@"
