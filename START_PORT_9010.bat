@echo off
title NextScout - Port 9010
color 0A

echo.
echo ================================================
echo   NEXTSCOUT SERVER (PORT 9010)
echo ================================================
echo.

cd /d C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

echo [1/4] Cache temizleniyor...
php artisan config:clear >nul 2>&1
php artisan route:clear >nul 2>&1

echo [2/4] Database kontrol...
if not exist database\database.sqlite (
    echo Database olusturuluyor...
    type nul > database\database.sqlite
    php artisan migrate --force
)

echo [3/4] Test user kontrol...
php artisan tinker --execute="if(!\App\Models\User::where('email','admin@nextscout.com')->exists()){$u=\App\Models\User::create(['name'=>'Admin','email'=>'admin@nextscout.com','password'=>bcrypt('Admin123!'),'role'=>'admin']);echo 'Admin user created\n';}" 2>nul

echo [4/4] Server baslatiliyor (Port 9010)...
echo.
echo ================================================
echo   SERVER HAZIR!
echo ================================================
echo.
echo   Server:     http://127.0.0.1:9010
echo   API:        http://127.0.0.1:9010/api
echo   Admin:      http://127.0.0.1:9010/admin
echo.
echo   Test Login:
echo   - Email:    admin@nextscout.com
echo   - Sifre:    Admin123!
echo.
echo   index.html acmak icin:
echo   http://localhost:8088/index.html?api_base=http://127.0.0.1:9010/api
echo.
echo Server calisiyor... (Durdurmak: CTRL+C)
echo ================================================
echo.

php artisan serve --host=127.0.0.1 --port=9010
