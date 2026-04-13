#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/apps/scout_api}"
CADDY_DIR="${CADDY_DIR:-$HOME/caddy}"
LOG_DIR="${LOG_DIR:-$HOME/monitoring/scout_api}"
TIMESTAMP="$(date -Is)"
LOG_FILE="$LOG_DIR/monitor.log"
STATUS_FILE="$LOG_DIR/last_status.txt"
MAX_DISK_PERCENT="${MAX_DISK_PERCENT:-90}"
MAX_BACKUP_AGE_HOURS="${MAX_BACKUP_AGE_HOURS:-26}"

mkdir -p "$LOG_DIR"

app_compose() {
  docker compose --env-file "$APP_DIR/.env.production" -f "$APP_DIR/compose.prod.yml" "$@"
}

caddy_compose() {
  docker compose -f "$CADDY_DIR/compose.yml" "$@"
}

fail() {
  local message="$1"
  echo "[$TIMESTAMP] FAIL: $message" | tee -a "$LOG_FILE"
  echo "FAIL: $message" > "$STATUS_FILE"
  exit 1
}

pass() {
  local message="$1"
  echo "[$TIMESTAMP] OK: $message" | tee -a "$LOG_FILE"
}

require_ok() {
  local description="$1"
  shift
  if ! "$@"; then
    fail "$description"
  fi
}

http_code() {
  curl -k -sS -o /dev/null -w "%{http_code}" "$1"
}

containers="$(app_compose ps --format json)"
if [[ -z "$containers" ]]; then
  fail "app containers not found"
fi

for service in app nginx db redis queue scheduler; do
  if ! app_compose ps --services --status running | grep -qx "$service"; then
    fail "service '$service' is not running"
  fi
done

if ! caddy_compose ps --services --status running | grep -qx "caddy"; then
  fail "caddy is not running"
fi

api_up_code="$(http_code https://nextscout.pro/up)"
[[ "$api_up_code" == "200" ]] || fail "https://nextscout.pro/up returned $api_up_code"

ready_code="$(http_code https://api.nextscout.pro/up)"
[[ "$ready_code" == "200" ]] || fail "https://api.nextscout.pro/up returned $ready_code"

local_up_code="$(http_code http://127.0.0.1:8081/up)"
[[ "$local_up_code" == "200" ]] || fail "http://127.0.0.1:8081/up returned $local_up_code"

disk_percent="$(df -P / | awk 'NR==2 {gsub("%","",$5); print $5}')"
if (( disk_percent >= MAX_DISK_PERCENT )); then
  fail "disk usage at ${disk_percent}%"
fi

backup_root="$HOME/backups/scout_api"
latest_backup="$(find "$backup_root" -mindepth 1 -maxdepth 1 -type d | sort | tail -n 1)"
if [[ -z "$latest_backup" ]]; then
  fail "no backup directory found under $backup_root"
fi

backup_age_hours="$(( ( $(date +%s) - $(stat -c %Y "$latest_backup") ) / 3600 ))"
if (( backup_age_hours > MAX_BACKUP_AGE_HOURS )); then
  fail "latest backup is ${backup_age_hours}h old"
fi

pass "services healthy, endpoints OK, disk ${disk_percent}%, latest backup ${backup_age_hours}h old"
echo "OK" > "$STATUS_FILE"
