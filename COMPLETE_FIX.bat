@echo off
title NextScout - Complete Fix
color 0B

echo.
echo ================================================
echo   NEXTSCOUT BAGLANTI TAMAM EDILIYOR
echo ================================================
echo.

cd /d C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

echo [1/5] Database kontrol...
if not exist database\database.sqlite (
    echo Database olusturuluyor...
    type nul > database\database.sqlite
)

echo [2/5] Cache temizleniyor...
php artisan config:clear >nul 2>&1
php artisan route:clear >nul 2>&1
php artisan cache:clear >nul 2>&1

echo [3/5] Migration kontrol...
php artisan migrate:status | findstr "Ran" >nul
if %errorlevel% neq 0 (
    echo Migration calistiriliyor...
    php artisan migrate --force
)

echo [4/5] Test user olusturuluyor...
php artisan tinker --execute="if(!\App\Models\User::where('email','admin@nextscout.com')->exists()){$u=\App\Models\User::create(['name'=>'Admin','email'=>'admin@nextscout.com','password'=>bcrypt('Admin123!'),'role'=>'admin']);echo 'Admin user created: admin@nextscout.com / Admin123!\n';}"

echo [5/5] Server baslatiliyor...
echo.
echo ================================================
echo   HAZIR!
echo ================================================
echo.
echo TEST BILGILERI:
echo   Email:    admin@nextscout.com
echo   Sifre:    Admin123!
echo.
echo ADRESLER:
echo   Ana Sayfa:  http://127.0.0.1:8000
echo   Admin:      http://127.0.0.1:8000/admin
echo   API Test:   http://127.0.0.1:8000/api/ping
echo.
echo index.html: file:///C:/Users/Hp/Desktop/PhpstormProjects/untitled/index.html
echo.
echo Server calisiyor... (Durdurmak: CTRL+C)
echo ================================================
echo.

php artisan serve --host=127.0.0.1 --port=8000
