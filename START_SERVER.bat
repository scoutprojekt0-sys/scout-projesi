@echo off
echo ========================================
echo   NEXTSCOUT SERVER BASLATILIYOR
echo ========================================
echo.

cd C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

echo [1/2] Dizin: scout_api
cd

echo [2/2] Server baslatiliyor...
echo.
echo ========================================
echo   SERVER CALISTIRILDI!
echo ========================================
echo.
echo Admin Panel: http://127.0.0.1:8000/admin
echo Test Sayfasi: http://127.0.0.1:8000/admin-test
echo Ana Sayfa: http://127.0.0.1:8000
echo.
echo Server'i durdurmak icin: CTRL+C
echo.
php artisan serve
