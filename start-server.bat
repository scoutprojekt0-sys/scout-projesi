@echo off
echo ========================================
echo NextScout Platform - Starting Server
echo ========================================
echo.

cd /d e:\PhpstormProjects\untitled\scout_api

echo Checking Laravel installation...
if not exist "artisan" (
    echo ERROR: Laravel artisan not found!
    echo Please run: composer install
    pause
    exit /b
)

echo.
echo Starting Laravel development server...
echo.
echo ========================================
echo Server running at:
echo http://localhost:8000
echo ========================================
echo.
echo Press Ctrl+C to stop the server
echo.

php artisan serve

pause
