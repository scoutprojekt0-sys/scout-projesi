@echo off
title NextScout - Port 3001
color 0C

echo.
echo ================================================
echo   NEXTSCOUT SERVER (PORT 3001)
echo ================================================
echo.

cd /d C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

echo [1/3] Cache temizleniyor...
php artisan config:clear >nul 2>&1
php artisan route:clear >nul 2>&1

echo [2/3] Database kontrol...
if not exist database\database.sqlite (
    type nul > database\database.sqlite
    php artisan migrate --force >nul 2>&1
)

echo [3/3] Server baslatiliyor (Port 3001)...
echo.
echo ================================================
echo   SERVER HAZIR! (PORT 3001)
echo ================================================
echo.
echo   Server:     http://127.0.0.1:3001
echo   API:        http://127.0.0.1:3001/api
echo   Admin:      http://127.0.0.1:3001/admin
echo.
echo   TARAYICIDA AC (kopyala-yapistir):
echo   http://localhost:8088/index.html?api_base=http://127.0.0.1:3001/api
echo.
echo   Test Login:
echo   Email:  admin@nextscout.com
echo   Sifre:  Admin123!
echo.
echo Server calisiyor... (Durdurmak: CTRL+C)
echo ================================================
echo.

start http://localhost:8088/index.html?api_base=http://127.0.0.1:3001/api

php artisan serve --host=127.0.0.1 --port=3001
