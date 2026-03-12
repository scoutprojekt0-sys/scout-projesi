@echo off
echo ================================
echo Scout API - Composer-Free Start
echo ================================
echo.
echo WARNING: Composer not found, skipping dependency installation
echo If you have vendor/ folder, server will still work
echo.

cd /d c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean

echo [1/4] Checking .env file...
if not exist .env (
    echo .env not found, copying from .env.example...
    copy .env.example .env
) else (
    echo .env exists
)

echo.
echo [2/4] Generating application key...
php artisan key:generate --force
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Key generation failed
    pause
    exit /b 1
)

echo.
echo [3/4] Creating database...
if not exist database\database.sqlite (
    type nul > database\database.sqlite
    echo SQLite database created
) else (
    echo SQLite database exists
)

echo.
echo [4/4] Running migrations...
php artisan migrate --force
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Migration failed - trying fresh...
    php artisan migrate:fresh --force
)

echo.
echo Seeding database...
php artisan db:seed --class=SubscriptionPlanSeeder --force
php artisan db:seed --class=DemoDataSeeder --force

echo.
echo ================================
echo Server Starting!
echo ================================
echo.
echo Test URLs:
echo - http://localhost:8000/api/ping
echo - http://localhost:8000/api/news
echo - http://localhost:8000/api/billing/plans
echo.
echo Press Ctrl+C to stop
echo.

php artisan serve
