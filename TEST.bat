@echo off
color 0B
cls
echo.
echo ================================================
echo          NEXTSCOUT - HEMEN TEST ET!
echo ================================================
echo.
echo Anasayfa aciliyor...
echo.

cd /d e:\PhpstormProjects\untitled
start "" NEXTSCOUT_HOMEPAGE.html

timeout /t 1 /nobreak >nul

echo [OK] Tarayicida acildi!
echo.
echo Dosya: NEXTSCOUT_HOMEPAGE.html
echo Konum: e:\PhpstormProjects\untitled\
echo.

pause
