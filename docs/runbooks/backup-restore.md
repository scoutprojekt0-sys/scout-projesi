# Backup and Restore Runbook

## Scope

This runbook covers backup and restore for:
- database (`MariaDB` in current production baseline)
- application storage (`storage/`)
- deployment secrets (`.env.production`)

## Backup Procedure

### 1) Automated backup script

Production server:

```bash
cd ~/apps/scout_api
bash scripts/backup_prod.sh
```

Default output path:

```bash
~/backups/scout_api/<timestamp>/
```

Artifacts:

- `database.sql.gz`
- `storage.tar.gz`
- `.env.production`
- `git_commit.txt`
- `migrate_status.txt`

### 2) Optional custom backup location

```bash
cd ~/apps/scout_api
BACKUP_ROOT=/mnt/backups/scout_api bash scripts/backup_prod.sh
```

### 3) Metadata

Record:
- commit hash
- migration state (`php artisan migrate:status`)
- backup timestamp

## Restore Procedure

### 1) Stop writes

- Put app in maintenance mode or stop app traffic.

### 2) Restore database

```bash
gunzip -c ~/backups/scout_api/<timestamp>/database.sql.gz | \
docker compose --env-file .env.production -f compose.prod.yml exec -T db \
  mariadb -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
```

### 3) Restore storage

```bash
docker compose --env-file .env.production -f compose.prod.yml exec -T app \
  sh -lc 'rm -rf /var/www/html/storage/*'

tar -xzf ~/backups/scout_api/<timestamp>/storage.tar.gz -C /
```

### 4) Restore environment file

```bash
cp ~/backups/scout_api/<timestamp>/.env.production ~/apps/scout_api/.env.production
```

### 5) Validate integrity

- `php artisan migrate:status`
- `php artisan test --filter=AuthSecurityHardeningTest`
- smoke-check key endpoints

## RTO/RPO Targets (baseline)

- Target RTO: 60 minutes
- Target RPO: 24 hours

## Cron Example

Nightly backup at `03:15`:

```bash
15 3 * * * cd /home/deploy/apps/scout_api && /usr/bin/bash scripts/backup_prod.sh >> /home/deploy/backups/scout_api/backup.log 2>&1
```
