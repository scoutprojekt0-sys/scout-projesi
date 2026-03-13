# Front Controller Gecis Plani

Hedef: tum istekleri `public/index.php` uzerinden yonetmek.

Durum: tamamlandi.

1. `public/index.php` route map ve method kontrolu ile mevcut PHP dosyalarina yonlendirir.
2. Header linkleri ve form action/redirect noktalarinda `app_route_url()` ve `app_redirect_route()` kullanilir.
3. `public/.htaccess` ile mevcut dosya/dizinler haric tum istekler `public/index.php` dosyasina yonlendirilir.
4. Statik varliklar `public/styles` ve `public/images` altindan servis edilir.

Not: Kok dizindeki eski giris dosyalari geriye donuk uyumluluk icin mevcut, aktif akista `public/` web root kullanilmalidir.