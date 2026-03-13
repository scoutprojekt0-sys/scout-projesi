@echo off
echo ============================================
echo NEXTSCOUT - YENI OZELLIKLERI KURULUM
echo ============================================
echo.

cd scout_api

echo [1/5] Composer paketlerini kontrol ediliyor...
if not exist "vendor\stripe" (
    echo Stripe SDK yukleniyor...
    composer require stripe/stripe-php
    echo ✓ Stripe SDK yuklendi
) else (
    echo ✓ Stripe SDK zaten yuklu
)
echo.

echo [2/5] Database migration'lari calistiriliyor...
php artisan migrate --force
echo ✓ Migration'lar tamamlandi
echo.

echo [3/5] Subscription paketleri seed ediliyor...
php artisan db:seed --class=SubscriptionPlanSeeder
echo ✓ 4 abonelik paketi eklendi (Free, Scout Pro, Manager Pro, Club Premium)
echo.

echo [4/5] Cache temizleniyor...
php artisan config:clear
php artisan cache:clear
php artisan route:clear
echo ✓ Cache temizlendi
echo.

echo [5/5] Yeni route'lar optimize ediliyor...
php artisan route:cache
echo ✓ Route'lar cache'lendi
echo.

echo ============================================
echo ✓ KURULUM TAMAMLANDI!
echo ============================================
echo.
echo SONRAKI ADIMLAR:
echo.
echo 1. .env dosyasini duzenle ve Stripe key'lerini ekle:
echo    STRIPE_KEY=pk_test_...
echo    STRIPE_SECRET=sk_test_...
echo.
echo 2. Google Analytics ID'sini ekle:
echo    GOOGLE_ANALYTICS_ID=G-XXXXXXXXXX
echo.
echo 3. API sunucusunu baslat:
echo    php artisan serve
echo.
echo 4. Yeni API endpoint'leri test et:
echo    GET  /api/subscription/plans
echo    POST /api/analytics/pageview
echo    GET  /sitemap.xml
echo    GET  /robots.txt
echo.
echo DOKUMANTASYON:
echo - NEXTSCOUT_MISSING_FEATURES_ANALYSIS.md
echo - IMPLEMENTATION_GUIDE_MISSING_FEATURES.md
echo.
pause
