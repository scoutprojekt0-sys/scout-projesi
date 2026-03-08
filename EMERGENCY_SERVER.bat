@echo off
title NextScout - Emergency Server
color 0D

echo.
echo ================================================
echo   ACIL DURUM SERVERI - PORT 8765
echo ================================================
echo.

cd /d C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

REM Random port to avoid conflicts
set PORT=8765

echo Minimal kurulum yapiliyor...

if not exist database\database.sqlite (
    type nul > database\database.sqlite
)

if not exist .env (
    if exist .env.example (
        copy .env.example .env >nul
    )
)

php artisan config:clear >nul 2>&1
php artisan key:generate --force >nul 2>&1

echo.
echo ================================================
echo   ACIL SERVER HAZIR! (PORT %PORT%)
echo ================================================
echo.
echo   URL: http://127.0.0.1:%PORT%
echo.
echo   TARAYICIDA AC:
echo   http://localhost:%PORT%/index.html?api_base=http://127.0.0.1:%PORT%/api
echo.
echo Server calisiyor...
echo ================================================
echo.

start http://127.0.0.1:%PORT%

php -S 127.0.0.1:%PORT% -t public
