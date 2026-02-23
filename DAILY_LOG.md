# Daily Log

## 2026-02-20
Bugun yapilan ozet (kaydetmelik):

1. `index.php` icinde ust menu butonlari `auth.php`'ye baglandi.
   `GIRIS YAP` ve `UYE OL` artik `auth.php` aciyor.
2. `auth.php` olusturuldu.
   E-posta (kullanici adi) + sifre ile:
   - Kayit olma
   - Giris yapma
   - Cikis yapma
   islemleri calisiyor.
3. Kullanici verileri dosyada tutuluyor:
   `users.json` (ayni klasorde otomatik olusur).
4. Test adresleri:
   - Ana sayfa: `http://localhost/nextscout/`
   - Giris/Kayit: `http://localhost/nextscout/auth.php`

Yarin devam noktasi:
- Giris yapan kullaniciyi `index.php` header'da gostermek
- Istersen veritabanina gecmek (dosya yerine MySQL)
