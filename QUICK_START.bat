@echo off
title NextScout - Quick Start
color 0A

echo.
echo ================================================
echo   NEXTSCOUT QUICK START
echo ================================================
echo.

cd /d C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

REM Port 8000 kulanimda mi kontrol et
netstat -ano | findstr :8000 >nul
if %errorlevel% equ 0 (
    echo [!] Port 8000 kulanimda! Alternatif port 8088 kullanilacak...
    set PORT=8088
) else (
    echo [OK] Port 8000 musait
    set PORT=8000
)

echo.
echo [1/3] Cache temizleniyor...
php artisan config:clear >nul 2>&1
php artisan route:clear >nul 2>&1
php artisan view:clear >nul 2>&1

echo [2/3] Server baslatiliyor (Port: %PORT%)...
echo.
echo ================================================
echo   SERVER HAZIR!
echo ================================================
echo.
echo   Ana Sayfa:     http://127.0.0.1:%PORT%
echo   Admin Panel:   http://127.0.0.1:%PORT%/admin
echo   API Test:      http://127.0.0.1:%PORT%/api/ping
echo.
echo   File ile test: file:///C:/Users/Hp/Desktop/PhpstormProjects/untitled/index.html
echo.
echo [3/3] Server calisiyor... (Durdurmak: CTRL+C)
echo ================================================
echo.

php artisan serve --host=127.0.0.1 --port=%PORT%
