# Guvenlik Temeli Mini Lab

Bu mini uygulama su konulari pratik etmek icin hazirlandi:

- Guclu sifre kurallari
- `password_hash` / `password_verify`
- CSRF token kontrolu
- 5 hatali giriste 10 dakika gecici kilit (rate limit)
- Guvenlik loglari ve admin log ekrani
- Session guvenligi (strict mode, httpOnly cookie, idle timeout)

## Calistirma

```bash
php -S localhost:8080 -t .
```

Sonra tarayicida ac:

- `http://localhost:8080/index.php`
- `http://localhost:8080/admin_logs.php` (login sonrasi)

## Notlar

- Veritabani: `data/app.sqlite`
- Kilitleme anahtari: `username + IP`
- `admin_logs.php` filtreleri: event, username, from/to tarih
- Ornek egitim amaclidir; production icin yetkilendirme ve audit kapsamı daha da artirilmalidir.
