# Runbook + Deploy (Hizli Ozet)

## 1. Ortam Hazirligi

- PHP 8.x ve MySQL/MariaDB calisiyor olmali.
- `public/` web root olarak ayarlanmali.
- `logs/` ve `backups/` klasorleri yazilabilir olmali.
- `.env` icinde en az su alanlar dolu olmali:
  - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET`
  - `APP_ENV` (`prod`), `APP_DEBUG` (`0`)

## 2. Lokal Calistirma

```powershell
C:\xampp\php\php.exe -S localhost:8088 -t C:\Users\Hp\PhpstormProjects\untitled\workshop\99_Miscellaneous\Refactoring\public
```

- Ana sayfa: `http://localhost:8088/index.php?r=home`
- Dashboard (admin/editor): `http://localhost:8088/index.php?r=dashboard`

## 3. DB Yapisi / Migration

- Uygulama ilk acilista gerekli tablolari olusturur.
- `audit_logs` tablo su olaylari tutar:
  - `add`, `edit`, `delete`
  - `password_change`
  - `login_success`, `login_fail`, `logout`

## 4. Rol/Yetki Ozeti

- `viewer`: sadece goruntuleme
- `editor`: icerik ekle/duzenle + dashboard
- `admin`: editor yetkileri + silme

## 5. Backup / Restore

Backup:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/db_backup.ps1
```

- Script her calismada sadece son 7 adet `db-*.sql` dosyasini tutar.

Restore:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/db_restore.ps1 -SqlFile "C:\path\to\db-YYYYMMDD-HHMMSS.sql"
```

## 6. Hizli Test Akisi

```powershell
C:\xampp\php\php.exe scripts/regression_quick.php http://localhost:8088
```

Kontroller:

- home ve login sayfalari
- csrf negatif senaryo
- gecersiz login
- basarili login
- logout sonrasi protected route erisim engeli

## 7. Deploy Checklist (Kisa)

- `APP_ENV=prod`, `APP_DEBUG=0`
- web root `public/`
- DB baglantisi dogrulandi
- logs klasoru yazilabilir
- backup alindi
- `regression_quick.php` PASS
