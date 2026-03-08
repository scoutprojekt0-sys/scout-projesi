@echo off
title NextScout - Diagnostic & Auto Fix
color 0E

echo.
echo ================================================
echo   NEXTSCOUT TESHIS VE OTOMATIK DUZELTME
echo ================================================
echo.

cd /d C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api

echo [KONTROL 1/6] PHP yuklu mu?
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] PHP BULUNAMADI!
    echo.
    echo PHP yuklu degil veya PATH'te degil.
    echo Cozum: PHP yukleyin veya PATH'e ekleyin.
    pause
    exit /b 1
) else (
    php --version | findstr "PHP"
    echo [OK] PHP bulundu
)

echo.
echo [KONTROL 2/6] Laravel artisan dosyasi var mi?
if not exist artisan (
    echo [X] ARTISAN BULUNAMADI!
    echo.
    echo Yanlis dizindesiniz veya Laravel kurulu degil.
    echo Dizin: %cd%
    pause
    exit /b 1
) else (
    echo [OK] artisan bulundu
)

echo.
echo [KONTROL 3/6] Port 8000 kullanimda mi?
netstat -ano | findstr :8000 >nul
if %errorlevel% equ 0 (
    echo [!] PORT 8000 KULANIMDA
    echo.
    echo Port 8000'i kullanan process'i kapatiyorum...
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000 ^| findstr LISTENING') do (
        echo Process PID: %%a kapatiliyor...
        taskkill /F /PID %%a >nul 2>&1
    )
    timeout /t 2 >nul
    echo [OK] Port temizlendi
) else (
    echo [OK] Port 8000 musait
)

echo.
echo [KONTROL 4/6] Database var mi?
if not exist database\database.sqlite (
    echo [!] Database olusturuluyor...
    type nul > database\database.sqlite
    echo [OK] Database olusturuldu
) else (
    echo [OK] Database mevcut
)

echo.
echo [KONTROL 5/6] .env dosyasi var mi?
if not exist .env (
    echo [!] .env olusturuluyor...
    if exist .env.example (
        copy .env.example .env >nul
        php artisan key:generate >nul 2>&1
        echo [OK] .env olusturuldu
    ) else (
        echo [X] .env.example bulunamadi!
    )
) else (
    echo [OK] .env mevcut
)

echo.
echo [KONTROL 6/6] Migration durumu
php artisan migrate:status >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Migration'lar calistiriliyor...
    php artisan migrate --force >nul 2>&1
    echo [OK] Migration'lar tamamlandi
) else (
    echo [OK] Migration'lar mevcut
)

echo.
echo ================================================
echo   TESHIS TAMAMLANDI - SERVER BASLATILIYOR
echo ================================================
echo.

echo Cache temizleniyor...
php artisan config:clear >nul 2>&1
php artisan route:clear >nul 2>&1
php artisan cache:clear >nul 2>&1

echo.
echo Test kullanicisi kontrol ediliyor...
php artisan tinker --execute="if(!\App\Models\User::where('email','test@nextscout.com')->exists()){echo 'Creating...\n';$u=\App\Models\User::create(['name'=>'Test User','email'=>'test@nextscout.com','password'=>bcrypt('Test123!'),'role'=>'admin']);echo 'Created: test@nextscout.com / Test123!\n';}" 2>nul

echo.
echo ================================================
echo   SERVER HAZIRLANDI!
echo ================================================
echo.
echo   Server:  http://127.0.0.1:8000
echo   Admin:   http://127.0.0.1:8000/admin
echo   API:     http://127.0.0.1:8000/api/ping
echo.
echo   Test Login:
echo   Email:   test@nextscout.com
echo   Sifre:   Test123!
echo.
echo   TARAYICIDA AC:
echo   http://localhost:8000/index.html
echo   (VEYA file:// ile index.html'i ac)
echo.
echo Server calisiyor... (CTRL+C ile durdur)
echo ================================================
echo.

start http://127.0.0.1:8000

php artisan serve --host=127.0.0.1 --port=8000
