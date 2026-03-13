@echo off
title NextScout - Admin Dashboard Fix
color 0A

echo.
echo ================================================
echo   ADMIN DASHBOARD ACILAMAMA SORUNU - COZUM
echo ================================================
echo.

cd /d C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

echo [1/5] Cache temizleniyor...
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo.
echo [2/5] Database kontrol...
if not exist database\database.sqlite (
    echo Database olusturuluyor...
    type nul > database\database.sqlite
)

echo.
echo [3/5] Migration kontrol...
php artisan migrate --force

echo.
echo [4/5] Route'lar kontrol ediliyor...
php artisan route:list | findstr admin

echo.
echo [5/5] Server baslatiliyor...
echo.
echo ================================================
echo   ADMIN DASHBOARD COZULDU!
echo ================================================
echo.
echo   Simdi bu adresleri test et:
echo.
echo   1. Admin Dashboard:
echo      http://127.0.0.1:8000/admin
echo.
echo   2. Dashboard Admin (alternative):
echo      http://127.0.0.1:8000/dashboard/admin
echo.
echo   3. Test Giris:
echo      Email:  demo@nextscout.com
echo      Sifre:  Demo123!
echo.
echo   Tarayici otomatik acilacak...
echo ================================================
echo.

timeout /t 3 >nul
start http://127.0.0.1:8000/admin

php artisan serve --host=127.0.0.1 --port=8000
