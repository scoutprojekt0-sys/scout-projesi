@echo off
title NextScout - Kesin Cozum
color 0A
cls

echo.
echo ╔════════════════════════════════════════════════╗
echo ║     NEXTSCOUT - KESIN COZUM                   ║
echo ║     Tek Tiklama - Otomatik Kurulum            ║
echo ╚════════════════════════════════════════════════╝
echo.

cd /d "%~dp0scout_api"

echo [1/8] Dizin kontrol...
if not exist artisan (
    echo [HATA] Laravel bulunamadi!
    echo Dizin: %cd%
    pause
    exit /b 1
)
echo [OK] Laravel bulundu

echo.
echo [2/8] Database olusturuluyor...
if not exist database mkdir database
if not exist database\database.sqlite (
    type nul > database\database.sqlite
    echo [OK] Database olusturuldu
) else (
    echo [OK] Database mevcut
)

echo.
echo [3/8] Environment dosyasi hazirlaniyor...
if not exist .env (
    if exist .env.example (
        copy .env.example .env >nul
        echo [OK] .env kopyalandi
    )
)
php artisan key:generate --force >nul 2>&1
echo [OK] App key olusturuldu

echo.
echo [4/8] Cache temizleniyor...
php artisan config:clear >nul 2>&1
php artisan route:clear >nul 2>&1
php artisan cache:clear >nul 2>&1
php artisan view:clear >nul 2>&1
echo [OK] Cache temizlendi

echo.
echo [5/8] Migration calistiriliyor...
php artisan migrate --force >nul 2>&1
echo [OK] Database hazir

echo.
echo [6/8] Test kullanicisi olusturuluyor...
php artisan tinker --execute="try{if(!\App\Models\User::where('email','demo@nextscout.com')->exists()){\App\Models\User::create(['name'=>'Demo User','email'=>'demo@nextscout.com','password'=>bcrypt('Demo123!'),'role'=>'admin']);echo 'Demo user created\n';}}catch(\Exception $e){}" 2>nul
echo [OK] Test kullanicisi hazir
echo      Email: demo@nextscout.com
echo      Sifre: Demo123!

echo.
echo [7/8] Port kontrol ve temizleme...
netstat -ano | findstr :8000 >nul
if %errorlevel% equ 0 (
    echo [!] Port 8000 kullanimda - temizleniyor...
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000 ^| findstr LISTENING') do (
        taskkill /F /PID %%a >nul 2>&1
    )
    timeout /t 2 >nul
)
echo [OK] Port 8000 hazir

echo.
echo [8/8] Server baslatiliyor...
echo.
echo ╔════════════════════════════════════════════════╗
echo ║              SERVER HAZIR!                     ║
echo ╚════════════════════════════════════════════════╝
echo.
echo   ► Ana Sayfa:  http://127.0.0.1:8000
echo   ► Admin:      http://127.0.0.1:8000/admin
echo   ► API Test:   http://127.0.0.1:8000/api/ping
echo.
echo   ► Test Giris:
echo     Email:  demo@nextscout.com
echo     Sifre:  Demo123!
echo.
echo ╔════════════════════════════════════════════════╗
echo ║  TARAYICIDA BU ADRESI AC:                      ║
echo ║  http://127.0.0.1:8000                         ║
echo ╚════════════════════════════════════════════════╝
echo.
echo Server calisiyor... (Durdurmak: CTRL+C)
echo.

timeout /t 3 >nul
start http://127.0.0.1:8000

php artisan serve --host=127.0.0.1 --port=8000 2>&1
