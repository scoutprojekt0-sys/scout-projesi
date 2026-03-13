# Regression Turu

Kritik akislar:

1. `login`
- Gecerli admin ile giris
- Hatali sifre ile red
- CSRF olmadan POST denemesinde 419

2. `add page`
- Gecerli form ile yeni sayfa
- Bos alan ile validation hatasi

3. `edit page`
- Liste secimi -> edit ekranina gecis
- Basarili update

4. `delete page`
- Ana sayfa (id=1) silme denemesi red
- Diger sayfa basarili silme

Hizli otomasyon:

- `scripts/regression_quick.php`
- `home` ve `login` route 200
- `login` sayfasinda CSRF token ve session cookie
- `login` POST CSRF negatif test (419)
- `login` POST hatali sifre test (mesaj dogrulamasi)