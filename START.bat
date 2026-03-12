@echo off
echo ================================
echo Scout API Quick Start Script
echo ================================
echo.

cd /d c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean

echo [1/6] Checking .env file...
if not exist .env (
    echo .env not found, copying from .env.example...
    copy .env.example .env
) else (
    echo .env exists
)

echo.
echo [2/6] Installing dependencies...
where composer >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo Global composer not found, using local composer.phar...
    if not exist composer.phar (
        echo ERROR: composer.phar not found. Please install Composer.
        echo Download from: https://getcomposer.org/download/
        pause
        exit /b 1
    )
    php composer.phar install --no-interaction
) else (
    call composer install --no-interaction
)
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Composer install failed
    pause
    exit /b 1
)

echo.
echo [3/6] Generating application key...
php artisan key:generate --force
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Key generation failed
    pause
    exit /b 1
)

echo.
echo [4/6] Creating database...
if not exist database\database.sqlite (
    type nul > database\database.sqlite
    echo SQLite database created
) else (
    echo SQLite database exists
)

echo.
echo [5/6] Running migrations...
php artisan migrate --force
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Migration failed
    echo.
    echo Trying fresh migration...
    php artisan migrate:fresh --force
)

echo.
echo [6/6] Seeding database...
php artisan db:seed --class=SubscriptionPlanSeeder --force
php artisan db:seed --class=DemoDataSeeder --force

echo.
echo ================================
echo Setup Complete!
echo ================================
echo.
echo Starting server on http://localhost:8000
echo.
echo Test these URLs in your browser:
echo - http://localhost:8000/api/ping
echo - http://localhost:8000/api/news
echo - http://localhost:8000/api/billing/plans
echo.
echo Press Ctrl+C to stop the server
echo.

php artisan serve
