@echo off
REM Scout API - Windows Start Script
REM Türkçe Açıklama: Tüm gereken kontrolleri yapıp backend'i başlatır

echo ========================================
echo SCOUT API BACKEND - BASLAMA REHBERI
echo ========================================
echo.

REM Check .env
if not exist .env (
    echo [ERROR] .env dosyası bulunamadı!
    echo [INFO] .env.example'dan .env kopyalanıyor...
    copy .env.example .env
    echo [OK] .env oluşturuldu. Ayarlarınızı kontrol edin.
    pause
    exit /b 1
)

echo [OK] .env dosyası bulundu

REM Check vendor
if not exist vendor (
    echo [ERROR] vendor klasörü bulunamadı!
    echo [INFO] Composer bağımlılıkları yükleniyor...
    php composer.phar install
    if errorlevel 1 (
        echo [ERROR] Composer kurulumu başarısız oldu!
        pause
        exit /b 1
    )
)

echo [OK] vendor klasörü bulundu

REM Check APP_KEY
for /f "tokens=1,2 delims==" %%A in (.env) do (
    if "%%A"=="APP_KEY" (
        if "%%B"=="" (
            echo [ERROR] APP_KEY ayarlanmamış!
            echo [INFO] APP_KEY oluşturuluyor...
            php artisan key:generate
        )
    )
)

echo [OK] APP_KEY ayarlandı

REM Create database
if not exist database\database.sqlite (
    echo [INFO] SQLite database dosyası oluşturuluyor...
    type nul > database\database.sqlite
    echo [OK] database.sqlite oluşturuldu
)

REM Run migrations
echo [INFO] Database migrations çalıştırılıyor...
php artisan migrate --force
if errorlevel 1 (
    echo [WARNING] Migration hatası oluştu. Kontrol et.
)

REM Run seeders
echo [INFO] Database seeders çalıştırılıyor...
php artisan db:seed --force
if errorlevel 1 (
    echo [WARNING] Seeder hatası oluştu. Kontrol et.
)

echo.
echo ========================================
echo BACKEND SUNUCUSU BASLATILIYOR...
echo ========================================
echo.
echo API addresi: http://localhost:8000
echo Durdulmak için: CTRL + C
echo.
pause

REM Start server
php artisan serve
