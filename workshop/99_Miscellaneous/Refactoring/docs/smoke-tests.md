# Smoke Test Checklist

- [ ] `index.php` aciliyor ve menude sayfalar listeleniyor.
- [ ] Gecersiz `pageid` ile `Page not found` goruluyor.
- [ ] Login formu CSRF token olmadan 419 donuyor.
- [ ] Register formu ayni username ile duplicate hatasi veriyor.
- [ ] Admin olmayan kullanici `add/edit/delete` sayfalarina giremiyor.
- [ ] Session timeout dolunca yeni istek login ister hale geliyor.
- [ ] Hata durumunda `logs/app-YYYY-MM-DD.log` dosyasina satir yaziliyor.
- [ ] `r=password.change` ekraninda mevcut sifre dogrulama ve guclu sifre kurallari calisiyor.
- [ ] Ayni kullanici + IP icin 6 hatali login denemesinde rate-limit kilidi aktif oluyor.
- [ ] Yeni sifre ile login basarili, eski sifre ile login basarisiz.