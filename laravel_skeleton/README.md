# Laravel MVP Skeleton

Bu klasor, Scout platformu icin hizli baslangic iskeletidir.

## Icerik
- `database/migrations`: MVP tablolari
- `routes/api.php`: endpoint tanimlari
- `app/Http/Controllers/Api`: controller iskeletleri

## Kullanim
1. Gercek bir Laravel projesi olustur.
2. Bu klasordeki dosyalari Laravel projesindeki ayni path'lere kopyala.
3. Sanctum kur: `composer require laravel/sanctum`
4. Sanctum migration + publish: `php artisan vendor:publish --provider=\"Laravel\\Sanctum\\SanctumServiceProvider\"`
5. Migration calistir: `php artisan migrate`
6. Controller TODO alanlarini business logic ile doldur.
