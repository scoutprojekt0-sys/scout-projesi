@echo off
echo ========================================
echo   ADMIN DASHBOARD FIX SCRIPT
echo ========================================
echo.

cd scout_api

echo [1/5] Route cache temizleniyor...
php artisan route:clear

echo [2/5] Config cache temizleniyor...
php artisan config:clear

echo [3/5] View cache temizleniyor...
php artisan view:clear

echo [4/5] Application cache temizleniyor...
php artisan cache:clear

echo [5/5] Tum cache temizleniyor...
php artisan optimize:clear

echo.
echo ========================================
echo   TAMAMLANDI!
echo ========================================
echo.
echo Simdi sunu dene:
echo   1. php artisan serve
echo   2. http://127.0.0.1:8000/admin-test
echo   3. http://127.0.0.1:8000/admin
echo.
pause
