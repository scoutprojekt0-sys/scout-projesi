@echo off
cls
color 0C
echo ========================================
echo   ESKİ ANASAYFA DOSYALARINI SİLİYORUM
echo ========================================
echo.

cd /d e:\PhpstormProjects\untitled

echo [1/5] NEXTSCOUT_HOMEPAGE.html siliniyor...
if exist "NEXTSCOUT_HOMEPAGE.html" del /q "NEXTSCOUT_HOMEPAGE.html"

echo [2/5] Launch dosyalari siliniyor...
if exist "LAUNCH_NEXTSCOUT.bat" del /q "LAUNCH_NEXTSCOUT.bat"
if exist "OPEN.bat" del /q "OPEN.bat"
if exist "TEST.bat" del /q "TEST.bat"

echo [3/5] Eski homepage dokumanları siliniyor...
if exist "NEXTSCOUT_HOMEPAGE_COMPLETE.md" del /q "NEXTSCOUT_HOMEPAGE_COMPLETE.md"
if exist "NEXTSCOUT_HOMEPAGE_FINAL.md" del /q "NEXTSCOUT_HOMEPAGE_FINAL.md"
if exist "NEXTSCOUT_VISUAL_SHOWCASE.md" del /q "NEXTSCOUT_VISUAL_SHOWCASE.md"
if exist "HOMEPAGE_TABS_DESIGN.html" del /q "HOMEPAGE_TABS_DESIGN.html"

echo [4/5] Standart template'ler siliniyor...
if exist "PROFILE_CARD_DESIGN.html" del /q "PROFILE_CARD_DESIGN.html"
if exist "PUBLIC_HOME_PAGE.html" del /q "PUBLIC_HOME_PAGE.html"
if exist "AUTHENTICATED_DASHBOARD.html" del /q "AUTHENTICATED_DASHBOARD.html"
if exist "DASHBOARD_DESIGN.html" del /q "DASHBOARD_DESIGN.html"

echo [5/5] Gereksiz yardim dosyalari siliniyor...
if exist "NASIL_AÇILIR.md" del /q "NASIL_AÇILIR.md"

echo.
echo ========================================
echo TAMAMLANDI! ESKİ DOSYALAR SİLİNDİ
echo ========================================
echo.
echo Akşam için hazırız!
echo AKŞAM_PLAN.md dosyasina bak
echo.

pause
