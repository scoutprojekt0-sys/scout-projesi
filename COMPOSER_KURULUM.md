# COMPOSER KURULUMU

Composer bulunamadı. İki seçenek var:

## SEÇenek 1: Composer Kur (Önerilen)

1. Bu linke git: https://getcomposer.org/download/
2. **Composer-Setup.exe** indir
3. Çalıştır ve kurulumu tamamla
4. Terminali kapat ve yeniden aç
5. `START.bat` çalıştır

## SEÇENEK 2: Composer'sız Devam Et (Hızlı)

Eğer daha önce `composer install` çalıştırdıysan ve `vendor/` klasörü varsa:

```cmd
START_NO_COMPOSER.bat
```

Bu script composer olmadan sunucuyu başlatır.

## SEÇENEK 3: Manuel Başlat

```cmd
cd c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

## VENDOR KLASÖRÜ VAR MI KONTROL ET

```cmd
cd c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
dir vendor
```

Eğer "File Not Found" yazarsa Composer kurmalısın.
Eğer klasör listesi gelirse `START_NO_COMPOSER.bat` çalıştır.
