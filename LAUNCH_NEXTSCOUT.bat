@echo off
cls
color 0A
echo.
echo ========================================
echo   NEXTSCOUT PLATFORM - ANASAYFA TEST
echo ========================================
echo.
echo Tarayiciyi aciyorum...
echo.

REM HTML dosyasini tarayicida ac
start "" "e:\PhpstormProjects\untitled\NEXTSCOUT_HOMEPAGE.html"

timeout /t 2 /nobreak >nul

echo.
echo [OK] Anasayfa tarayicida acildi!
echo.
echo ========================================
echo DOSYA KONUMU:
echo ========================================
echo.
echo e:\PhpstormProjects\untitled\NEXTSCOUT_HOMEPAGE.html
echo.
echo ========================================
echo DIGER TESTLER:
echo ========================================
echo.
echo Laravel Backend Test:
echo   1. cd e:\PhpstormProjects\untitled\scout_api
echo   2. php artisan serve
echo   3. http://localhost:8000
echo.
echo API Test:
echo   http://localhost:8000/api/homepage/complete
echo.
echo ========================================
echo.

pause
