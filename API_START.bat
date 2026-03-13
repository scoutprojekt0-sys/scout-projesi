@echo off
color 0B
echo.
echo ============================================================
echo   NEXTSCOUT API - BASIT BASLAT
echo ============================================================
echo.
echo   Baslatiliyor: http://127.0.0.1:9000
echo.
echo ============================================================
echo.

cd /d e:\PhpstormProjects\untitled\scout_api

if not exist "artisan" (
    echo HATA: Laravel artisan bulunamadi!
    echo Lutfen su komutu calistir: composer install
    pause
    exit /b
)

echo API baslatiliyor...
echo.

php artisan serve --host=127.0.0.1 --port=9000

pause
