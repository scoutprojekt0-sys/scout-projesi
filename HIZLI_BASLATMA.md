# Scout API - Hızlı Başlangıç

## ADIM 1: Projeyi Başlat

Çift tıkla:
```
START.bat
```

Bu script otomatik olarak:
- ✅ .env dosyasını oluşturur
- ✅ Composer bağımlılıklarını yükler
- ✅ APP_KEY üretir
- ✅ Database oluşturur
- ✅ Migration'ları çalıştırır
- ✅ Demo data ekler
- ✅ Sunucuyu başlatır

## ADIM 2: Test Et

Yeni bir terminal aç ve çift tıkla:
```
TEST_API.bat
```

Veya tarayıcıda aç:
- http://localhost:8000/api/ping
- http://localhost:8000/api/news
- http://localhost:8000/api/billing/plans

## SORUN GİDERME

### "Class not found" hatası
```cmd
composer dump-autoload
```

### Database hatası
```cmd
php artisan migrate:fresh --seed
```

### Port zaten kullanımda
```cmd
php artisan serve --port=8001
```

## MANUEL BAŞLATMA

Eğer START.bat çalışmazsa:

```cmd
cd c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

## ENDPOINT'LER

### Public Endpoints (Auth gerektirmez)
- GET `/api/ping` - Health check
- GET `/api/news` - Haber listesi
- GET `/api/billing/plans` - Abonelik planları
- GET `/api/public/players` - Oyuncu listesi
- GET `/api/trending/week` - Trend oyuncular
- GET `/api/rising-stars` - Yükselen yıldızlar

### Protected Endpoints (Token gerekir)
- POST `/api/auth/register` - Kayıt ol
- POST `/api/auth/login` - Giriş yap
- GET `/api/auth/me` - Profil (token gerekli)

## DEMO KULLANICI

Giriş yapmak için:
```
Email: player1@demo.com
Password: password
```

## DESTEK

Sorun yaşıyorsan:
1. `START.bat` çalıştır
2. Hataları oku
3. `php artisan route:list` komutuyla route'ları kontrol et
