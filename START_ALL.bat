@echo off
REM Scout API - Master Start Script (Tüm uygulamalar)
REM Bu script backend, frontend ve hatta mobil uygulamayı da açmaya hazır

setlocal enabledelayedexpansion

set BACKEND_DIR=c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
set FRONTEND_DIR=c:\Users\Hp\Desktop\PhpstormProjects\untitled
set MOBILE_DIR=c:\Users\Hp\Desktop\PhpstormProjects\scout_mobile

cls
echo ====================================================================
echo.
echo     ███████╗ ██████╗ ██╗   ██╗████████╗ ██████╗  █████╗
echo     ██╔════╝██╔════╝ ██║   ██║╚══██╔══╝██╔═══██╗██╔══██╗
echo     ███████╗██║  ███╗██║   ██║   ██║   ██║   ██║███████║
echo     ╚════██║██║   ██║██║   ██║   ██║   ██║   ██║██╔══██║
echo     ███████║╚██████╔╝╚██████╔╝   ██║   ╚██████╔╝██║  ██║
echo     ╚══════╝ ╚═════╝  ╚═════╝    ╚═╝    ╚═════╝ ╚═╝  ╚═╝
echo.
echo ====================================================================
echo SCOUT API - ANA BASLAMA MENUSU
echo ====================================================================
echo.
echo Aşağıdakilerden birini seçin:
echo.
echo  [1] Backend'i başlat (API Server - http://localhost:8000)
echo  [2] Frontend'i başlat (Web App - http://localhost:3000)
echo  [3] Mobile'ı başlat (Flutter - Cihazda çalışacak)
echo  [4] Backend + Frontend (İkisini birlikte)
echo  [5] Herkesi başlat (Backend + Frontend + Mobile)
echo  [6] Veritabanını sıfırla (Tüm veriler silinecek!)
echo  [7] Sorun gider (Hata teşhisi)
echo  [0] Çık
echo.
set /p choice="Seçiminiz (0-7): "

if "%choice%"=="1" goto START_BACKEND
if "%choice%"=="2" goto START_FRONTEND
if "%choice%"=="3" goto START_MOBILE
if "%choice%"=="4" goto START_BOTH
if "%choice%"=="5" goto START_ALL
if "%choice%"=="6" goto RESET_DB
if "%choice%"=="7" goto TROUBLESHOOT
if "%choice%"=="0" goto END

echo [ERROR] Geçersiz seçim!
timeout /t 2
cls
goto MENU

REM ============================================================
:START_BACKEND
REM ============================================================
cls
echo ========================================
echo BACKEND BASLANIYOR...
echo ========================================
cd /d "%BACKEND_DIR%"

REM Kontroller
if not exist .env (
    echo [ERROR] .env bulunamadı!
    copy .env.example .env
    echo [OK] .env oluşturuldu
)

if not exist vendor (
    echo [INFO] Composer dependencies kurulması...
    php composer.phar install
)

REM Key check
for /f "tokens=2 delims==" %%i in ('findstr "APP_KEY" .env') do set KEY=%%i
if "!KEY!"=="" (
    echo [INFO] APP_KEY oluşturuluyor...
    php artisan key:generate
)

REM Database
if not exist database\database.sqlite (
    type nul > database\database.sqlite
)

echo [INFO] Migrations çalıştırılıyor...
php artisan migrate --force

echo [INFO] Seeders çalıştırılıyor...
php artisan db:seed --force

echo.
echo ========================================
echo BACKEND ÇALIŞIYOR!
echo ========================================
echo.
echo API: http://localhost:8000
echo API Docs: http://localhost:8000/api/ping
echo.
echo Durdulmak için CTRL+C
echo.

php artisan serve
pause
goto MENU

REM ============================================================
:START_FRONTEND
REM ============================================================
cls
echo ========================================
echo FRONTEND BASLANIYOR...
echo ========================================
cd /d "%FRONTEND_DIR%"

if not exist node_modules (
    echo [INFO] NPM dependencies kurulması...
    call npm install
)

echo.
echo ========================================
echo FRONTEND ÇALIŞIYOR!
echo ========================================
echo.
echo Frontend: http://localhost:3000 (veya görüntülenen adres)
echo API: http://localhost:8000/api
echo.
echo Durdulmak için CTRL+C
echo.

call npm run dev
pause
goto MENU

REM ============================================================
:START_MOBILE
REM ============================================================
cls
echo ========================================
echo MOBIL UYGULAMA BASLANIYOR...
echo ========================================
cd /d "%MOBILE_DIR%"

if not exist pubspec.lock (
    echo [INFO] Flutter dependencies kurulması...
    flutter pub get
)

echo.
echo ========================================
echo FLUTTER CALISTIRILIYOR!
echo ========================================
echo.
echo Cihazınızın bağlı olduğundan emin olun
echo.

flutter run
pause
goto MENU

REM ============================================================
:START_BOTH
REM ============================================================
echo [INFO] Backend ve Frontend birlikte açılacak...
echo.

REM Backend'i başlat (arka planda)
start "Scout Backend" cmd /k "cd /d %BACKEND_DIR% && php artisan serve"
timeout /t 3

REM Frontend'i başlat
cd /d "%FRONTEND_DIR%"
if not exist node_modules call npm install
call npm run dev
pause
goto MENU

REM ============================================================
:START_ALL
REM ============================================================
echo [INFO] Tüm uygulamalar açılacak...
echo.
echo 1. Backend başlatılıyor...
start "Scout Backend" cmd /k "cd /d %BACKEND_DIR% && php artisan serve"
timeout /t 3

echo 2. Frontend başlatılıyor...
start "Scout Frontend" cmd /k "cd /d %FRONTEND_DIR% && npm run dev"
timeout /t 3

echo 3. Mobile başlatılıyor...
start "Scout Mobile" cmd /k "cd /d %MOBILE_DIR% && flutter run"

echo.
echo ========================================
echo TÜM UYGULAMALAR ÇALIŞIYOR!
echo ========================================
echo.
echo Backend: http://localhost:8000
echo Frontend: http://localhost:3000
echo Mobile: Cihazda çalışıyor
echo.
timeout /t 5
goto MENU

REM ============================================================
:RESET_DB
REM ============================================================
cls
echo ========================================
echo VERITABANI SIFIRLAMA
echo ========================================
echo.
echo UYARI: Tüm veriler silinecek!
echo.
set /p confirm="Emin misiniz? (evet/hayır): "
if /i not "%confirm%"=="evet" goto MENU

cd /d "%BACKEND_DIR%"
echo [INFO] Veritabanı sıfırlanıyor...
php artisan migrate:refresh --seed --force

echo [OK] Veritabanı sıfırlandı!
echo.
echo Test kullanıcıları:
echo - Email: player@test.com / scout@test.com / team@test.com
echo - Şifre: Password123!
echo.
pause
goto MENU

REM ============================================================
:TROUBLESHOOT
REM ============================================================
cls
echo ========================================
echo SORUN GIDERICI
echo ========================================
echo.

cd /d "%BACKEND_DIR%"

echo [1/5] PHP kontrol ediliyor...
php -v
echo.

echo [2/5] Composer kontrol ediliyor...
php composer.phar --version
echo.

echo [3/5] Veritabanı kontrol ediliyor...
if exist database\database.sqlite (
    echo [OK] database.sqlite mevcut
) else (
    echo [ERROR] database.sqlite bulunamadı!
)
echo.

echo [4/5] .env kontrol ediliyor...
if exist .env (
    echo [OK] .env mevcut
) else (
    echo [ERROR] .env bulunamadı!
)
echo.

echo [5/5] Log dosyaları kontrol ediliyor...
if exist storage\logs\laravel.log (
    echo [OK] Log dosyası mevcut
    echo.
    echo Son 20 log satırı:
    powershell -Command "Get-Content storage\logs\laravel.log -Tail 20"
) else (
    echo [WARNING] Log dosyası henüz oluşturulmadı
)
echo.
echo ========================================
echo Sorun giderici tamamlandı
echo ========================================
echo.
pause
goto MENU

REM ============================================================
:END
REM ============================================================
echo.
echo Hoşça kalın! 👋
echo.
exit /b 0

REM ============================================================
:MENU
REM ============================================================
cls
goto START_BACKEND
