# Incident Quick Response Runbook

## 0-5 Dakika: Ilk Stabilizasyon
1. Kullanici etkisini not et: hangi ekran, hangi hata kodu, ne zaman basladi.
2. Son degisiklik var mi kontrol et (deploy/config/db).
3. Uygulama logunu ac: `logs/app-YYYY-MM-DD.log`.
4. Gerekirse gecici olarak yeni deploy'u durdur.

## Hata Koduna Gore Hizli Mudahale

### HTTP 500
- `logs/app-YYYY-MM-DD.log` icinde son satirlari kontrol et.
- PHP syntax hatasi supheliyse ilgili dosyada lint calistir.
- DB baglantisi / query hatasi varsa `.env` ve DB erisimi dogrula.
- Son degisiklikten sonra basladiysa ilgili dosyayi geri al veya hotfix gec.

### HTTP 419 (CSRF)
- Formda `csrf_token` gizli alani var mi kontrol et.
- Session aktif mi ve timeout nedeniyle sifirlanmis mi bak.
- Reverse proxy/cookie ayarlari (path/domain/samesite) dogru mu kontrol et.

### HTTP 429 benzeri login kilidi (rate-limit)
- Kullaniciya lock suresi bilgisini ver.
- Gerekirse `logs/login_attempts.json` icinde ilgili kaydi temizle.
- Bruteforce supheliyse IP bazli engelleme ekle.

### Login Basarisiz (dogru sifreye ragmen)
- `users.password` kolon tipi `VARCHAR(255)` mi kontrol et.
- Hash formati bcrypt/argon2 mi kontrol et.
- Gecici admin reset gerekiyorsa kontrollu reset proseduru uygula.

### Yetkisiz Erisim / Admin Gorunurlugu Sorunu
- Session icinde `userid` var mi kontrol et.
- `is_admin()` cache TTL dolumunu bekle veya session yenile.
- `users_in_roles` kayitlarini dogrula.

## Operasyonel Kontrol Listesi
- [ ] Problem kapsamý belirlendi (kac kullanici etkileniyor)
- [ ] Log kaydi alindi ve hata nedeni siniflandi
- [ ] Gecici mitigasyon uygulandi
- [ ] Kalici duzeltme uygulandi
- [ ] Regression smoke (login/add/edit/delete) calistirildi
- [ ] Olay notu dokumante edildi

## Hizli Komutlar
- PHP built-in server (local):
`C:\xampp\php\php.exe -S localhost:8088 -t C:\Users\Hp\PhpstormProjects\untitled\workshop\99_Miscellaneous\Refactoring\public`

- Rate-limit kaydini temizleme (local):
`Remove-Item "C:\Users\Hp\PhpstormProjects\untitled\workshop\99_Miscellaneous\Refactoring\logs\login_attempts.json" -Force`

## Olay Sonrasi (Postmortem)
1. Kök neden (root cause) yaz.
2. Tetikleyici degisiklik/koþulu yaz.
3. Tekrari engelleyecek aksiyonu backlog'a ekle.
4. Gerekli test/checklist maddelerini guncelle.