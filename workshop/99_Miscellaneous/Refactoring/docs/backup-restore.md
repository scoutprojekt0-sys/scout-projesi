# Backup and Restore

## Hazirlik
- `.env` dosyasinda DB ayarlari dogru olmali.
- XAMPP kullaniyorsan `C:\xampp\mysql\bin\mysqldump.exe` ve `mysql.exe` mevcut olmali.

## DB Backup
PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1
```

Opsiyonlar:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1 -WithEnv
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1 -OutputDir "C:\backup\cms"
```

Uretim:
- `backups/db-YYYYMMDD-HHMMSS.sql`
- `-WithEnv` verilirse `backups/env-YYYYMMDD-HHMMSS.bak`

## DB Restore
```powershell
powershell -ExecutionPolicy Bypass -File scripts/db_restore.ps1 -SqlFile "C:\path\to\db-YYYYMMDD-HHMMSS.sql"
```

## Guvenlik Notu
- `.env` dosyasini public alana koyma.
- Backup dosyalarini sifreli disk/guvenli depolama alaninda sakla.
- Restore oncesi mevcut DB backup al.