@echo off
echo ================================
echo Scout API - Complete Setup
echo ================================
echo.

cd /d c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean

REM Check if composer is available
where composer >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [1/6] Composer found - installing dependencies...
    composer install --no-interaction
    goto :continue
)

REM Check if local composer.phar exists
if exist composer.phar (
    echo [1/6] Using local composer.phar - installing dependencies...
    php composer.phar install --no-interaction
    goto :continue
)

REM Composer not found
echo [1/6] ERROR: Composer not found!
echo.
echo Please run: INSTALL_COMPOSER.bat
echo Or manually download: https://getcomposer.org/download/
echo.
pause
exit /b 1

:continue

echo.
echo [2/6] Checking .env file...
if not exist .env (
    copy .env.example .env
    echo .env created
) else (
    echo .env exists
)

echo.
echo [3/6] Generating application key...
php artisan key:generate --force

echo.
echo [4/6] Creating database...
if not exist database\database.sqlite (
    type nul > database\database.sqlite
    echo SQLite database created
)

echo.
echo [5/6] Running migrations and seeders...
php artisan migrate:fresh --seed --force

echo.
echo [6/6] Starting server...
echo.
echo ================================
echo Setup Complete! Server starting...
echo ================================
echo.
echo API is now available at:
echo - http://localhost:8000/api/ping
echo - http://localhost:8000/api/news
echo - http://localhost:8000/api/billing/plans
echo.
echo Press Ctrl+C to stop the server
echo.

php artisan serve
