@echo off
title NextScout - Server Status Check
color 0E

echo.
echo ================================================
echo   NEXTSCOUT SERVER DURUM KONTROLU
echo ================================================
echo.

cd /d C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

echo [1/4] PHP kontrolu...
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] PHP bulunamadi! PHP yuklu mu?
    pause
    exit /b 1
) else (
    echo [OK] PHP yuklü
)

echo.
echo [2/4] Laravel artisan kontrolu...
if not exist artisan (
    echo [X] artisan dosyasi bulunamadi!
    echo [!] Yanlis dizindesiniz
    pause
    exit /b 1
) else (
    echo [OK] Laravel projesi bulundu
)

echo.
echo [3/4] Port kullanim kontrolu...
netstat -ano | findstr :8000 >nul
if %errorlevel% equ 0 (
    echo [!] Port 8000 KULANIMDA
    echo.
    echo Port 8000'i kullanan process:
    netstat -ano | findstr :8000
    echo.
    echo Alternatif: QUICK_START.bat kullanin (otomatik 8088'e gecer)
) else (
    echo [OK] Port 8000 musait
)

netstat -ano | findstr :8088 >nul
if %errorlevel% equ 0 (
    echo [!] Port 8088 KULANIMDA
) else (
    echo [OK] Port 8088 musait
)

echo.
echo [4/4] API endpoint testi...
echo.
echo Simdi test ediliyor...
php artisan serve --host=127.0.0.1 --port=8000 >nul 2>&1 &
timeout /t 2 >nul

curl -s http://127.0.0.1:8000/api/ping >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] API erislebilir
) else (
    echo [!] API erisemiyor - server baslatilmamis olabilir
)

echo.
echo ================================================
echo   DURUM RAPORU TAMAMLANDI
echo ================================================
echo.
echo Sonraki adim:
echo   1. Her sey OK ise: QUICK_START.bat calistir
echo   2. Sorun varsa: yukaridaki hatalari coz
echo.
pause
