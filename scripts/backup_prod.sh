#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/apps/scout_api}"
BACKUP_ROOT="${BACKUP_ROOT:-$HOME/backups/scout_api}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$BACKUP_ROOT/$TIMESTAMP"

cd "$APP_DIR"

if [[ ! -f ".env.production" ]]; then
  echo ".env.production not found in $APP_DIR" >&2
  exit 1
fi

mkdir -p "$BACKUP_DIR"

compose() {
  docker compose --env-file .env.production -f compose.prod.yml "$@"
}

read_env() {
  grep -E "^$1=" .env.production | head -n1 | cut -d= -f2-
}

DB_DATABASE="$(read_env DB_DATABASE)"
DB_USERNAME="$(read_env DB_USERNAME)"
DB_PASSWORD="$(read_env DB_PASSWORD)"

if [[ -z "$DB_DATABASE" || -z "$DB_USERNAME" || -z "$DB_PASSWORD" ]]; then
  echo "Database credentials missing in .env.production" >&2
  exit 1
fi

cp .env.production "$BACKUP_DIR/.env.production"
chmod 600 "$BACKUP_DIR/.env.production"

git rev-parse HEAD > "$BACKUP_DIR/git_commit.txt"
date -Is > "$BACKUP_DIR/backup_timestamp.txt"

compose ps > "$BACKUP_DIR/docker_ps.txt"
compose exec -T app php artisan migrate:status --no-interaction > "$BACKUP_DIR/migrate_status.txt"

compose exec -T db mariadb-dump \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  -u"$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" > "$BACKUP_DIR/database.sql"

gzip -f "$BACKUP_DIR/database.sql"

compose exec -T app tar -czf - -C /var/www/html storage > "$BACKUP_DIR/storage.tar.gz"

cat <<INFO
Backup complete:
  $BACKUP_DIR

Files:
  .env.production
  git_commit.txt
  backup_timestamp.txt
  docker_ps.txt
  migrate_status.txt
  database.sql.gz
  storage.tar.gz
INFO
