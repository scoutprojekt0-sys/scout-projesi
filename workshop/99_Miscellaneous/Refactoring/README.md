# Refactoring
This folder contains a sample project that is a perfect candidate for trying various refactorings in real-life. Try cleaning the code a bit!

## Local Run

```powershell
C:\xampp\php\php.exe -S localhost:8088 -t C:\Users\Hp\PhpstormProjects\untitled\workshop\99_Miscellaneous\Refactoring\public
```

Open:

- `http://localhost:8088/index.php?r=home`

## Deploy Notes

- Web root should point to `public/`.
- `.env` should be set to production values (`APP_ENV=prod`, `APP_DEBUG=0`).
- Ensure `logs/` is writable by web user.

## Backup

```powershell
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1
```

## Restore

```powershell
powershell -ExecutionPolicy Bypass -File scripts/db_restore.ps1 -SqlFile "C:\path\to\db-YYYYMMDD-HHMMSS.sql"
```

## Quick Regression

```powershell
C:\xampp\php\php.exe scripts/regression_quick.php http://localhost:8088
```

## Runbook + Deploy

- `docs/runbook-deploy.md`
