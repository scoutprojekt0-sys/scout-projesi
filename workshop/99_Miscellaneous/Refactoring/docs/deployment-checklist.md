# Deployment Checklist

## Teknik Hazirlik
1. `.env` dosyasini prod degerleriyle olustur (`APP_ENV=prod`, `APP_DEBUG=0`).
2. Migration dosyalarini sira ile uygula (`migrations/001_create_core_tables.sql`, `migrations/002_add_unique_users_username.sql`).
3. `users.password` kolonunu `VARCHAR(255)` olarak dogrula.
4. Web server root'unu `public/` olacak sekilde ayarla.
5. `logs/` klasorunun web kullanicisi tarafindan yazilabilir oldugunu dogrula.

## Guvenlik Dogrulamasi
1. Admin default sifresinin degistirildigini dogrula (`r=password.change`).
2. Login rate-limit testini gec (6 hatali deneme sonrasi kilit).
3. CSRF ve auth gerektiren endpointleri manuel test et.
4. Session timeout ve tekrar login davranisini dogrula.

## Yayin Oncesi Kontrol
1. Smoke test listesini tamamla.
2. Regression turunu tamamla (`login/add/edit/delete`).
3. Incident runbook (`docs/incident-response.md`) ekibini bilgilendir.
