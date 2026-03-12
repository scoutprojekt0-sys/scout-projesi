# Backup and Restore Runbook

## Scope

This runbook covers backup and restore for:
- database (`sqlite` in current baseline)
- media files (`storage/app/public/media`)

## Backup Procedure

### 1) Database snapshot

SQLite example:

```bash
cp database/database.sqlite backups/database-$(date +%Y%m%d-%H%M%S).sqlite
```

### 2) Media snapshot

```bash
tar -czf backups/media-$(date +%Y%m%d-%H%M%S).tar.gz storage/app/public/media
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
cp backups/database-<timestamp>.sqlite database/database.sqlite
```

### 3) Restore media

```bash
tar -xzf backups/media-<timestamp>.tar.gz -C /
```

### 4) Validate integrity

- `php artisan migrate:status`
- `php artisan test --filter=AuthSecurityHardeningTest`
- smoke-check key endpoints

## RTO/RPO Targets (baseline)

- Target RTO: 60 minutes
- Target RPO: 24 hours
