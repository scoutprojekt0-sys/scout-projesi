@echo off
cls
echo ========================================
echo   NEXTSCOUT - PROJE TEMIZLEME
echo ========================================
echo.
echo UYARI: Bu script gereksiz dosyalari silecek!
echo.
echo Silinecek dosyalar:
echo - Eski HTML test sayfalari
echo - Gereksiz PDF dosyalari
echo - Gecici workshop dosyalari
echo - Fazla dokumantasyon dosyalari
echo.
echo ========================================
echo.

set /p confirm="Devam etmek istiyor musun? (E/H): "
if /i not "%confirm%"=="E" (
    echo Iptal edildi.
    pause
    exit /b
)

echo.
echo Temizlik basliyor...
echo.

cd /d e:\PhpstormProjects\untitled

REM Ana klasor temizligi
echo [1/5] Ana klasor temizleniyor...
if exist "CODE_OF_CONDUCT.md" del /q CODE_OF_CONDUCT.md
if exist "DAILY_LOG.md" del /q DAILY_LOG.md
if exist "composer.phar" del /q composer.phar
if exist "main.dart" del /q main.dart
if exist "socket_test.php" del /q socket_test.php
if exist "smoke-web.ps1" del /q smoke-web.ps1

REM Eski HTML dosyalari (NEXTSCOUT_HOMEPAGE.html haric)
echo [2/5] Eski HTML dosyalari siliniyor...
if exist "about.html" del /q about.html
if exist "ajanda.html" del /q ajanda.html
if exist "antranor-giris.html" del /q antranor-giris.html
if exist "dashboard.html" del /q dashboard.html
if exist "hizli-karsilastir.html" del /q hizli-karsilastir.html
if exist "hukuk-ofisi.html" del /q hukuk-ofisi.html
if exist "index.html" del /q index.html
if exist "kulup-ihtiyac.html" del /q kulup-ihtiyac.html
if exist "kvkk.html" del /q kvkk.html
if exist "league-standings.html" del /q league-standings.html
if exist "live-matches.html" del /q live-matches.html
if exist "login.html" del /q login.html
if exist "menejer-giris.html" del /q menejer-giris.html
if exist "mesajlarim.html" del /q mesajlarim.html
if exist "oyuncu-giris.html" del /q oyuncu-giris.html
if exist "profil.html" del /q profil.html
if exist "takim-giris.html" del /q takim-giris.html
if exist "transfer-market.html" del /q transfer-market.html

REM PDF ve workshop klasorleri
echo [3/5] PDF ve workshop klasorleri siliniyor...
if exist "pdf_pages" rd /s /q pdf_pages
if exist "translated_pdfs" rd /s /q translated_pdfs
if exist "workshop" rd /s /q workshop
if exist "laravel_skeleton" rd /s /q laravel_skeleton

REM Scout_api icindeki fazla dokumanlar
echo [4/5] Fazla dokumantasyon dosyalari siliniyor...
cd scout_api
if exist "ADMIN_PANEL_SYSTEM.md" del /q ADMIN_PANEL_SYSTEM.md
if exist "AMATEUR_MARKET_SYSTEM.md" del /q AMATEUR_MARKET_SYSTEM.md
if exist "AMATEUR_PLATFORM.md" del /q AMATEUR_PLATFORM.md
if exist "ANONYMOUS_MESSAGING.md" del /q ANONYMOUS_MESSAGING.md
if exist "BACKEND_ANALYSIS.md" del /q BACKEND_ANALYSIS.md
if exist "CHANGES.md" del /q CHANGES.md
if exist "COMPLETION_REPORT.md" del /q COMPLETION_REPORT.md
if exist "LEGAL_SYSTEM.md" del /q LEGAL_SYSTEM.md
if exist "LEGAL_SYSTEM_COMPLETE.md" del /q LEGAL_SYSTEM_COMPLETE.md
if exist "LOCALIZATION_COMPLETE.md" del /q LOCALIZATION_COMPLETE.md
if exist "LOCALIZATION_SYSTEM.md" del /q LOCALIZATION_SYSTEM.md
if exist "MESSAGING_COMPLETE.md" del /q MESSAGING_COMPLETE.md
if exist "MULTI_SPORT_COMPLETE.md" del /q MULTI_SPORT_COMPLETE.md
if exist "MULTI_SPORT_PLATFORM.md" del /q MULTI_SPORT_PLATFORM.md
if exist "PLATFORM_STATUS_REPORT.md" del /q PLATFORM_STATUS_REPORT.md
if exist "PROFILE_CARD_SYSTEM.md" del /q PROFILE_CARD_SYSTEM.md
if exist "PROFILE_MESSAGING_SEARCH_HELP.md" del /q PROFILE_MESSAGING_SEARCH_HELP.md
if exist "SPORT_CATEGORY_SYSTEM.md" del /q SPORT_CATEGORY_SYSTEM.md
if exist "TEAM_STATS_AND_LIVE_MATCHES.md" del /q TEAM_STATS_AND_LIVE_MATCHES.md
if exist "TEAM_STATS_COMPLETE.md" del /q TEAM_STATS_COMPLETE.md
if exist "TRANSFERMARKT_UPGRADE.md" del /q TRANSFERMARKT_UPGRADE.md
if exist "README_COMPLETE.md" del /q README_COMPLETE.md

REM Setup scriptleri
if exist "setup-amateur-platform.bat" del /q setup-amateur-platform.bat
if exist "setup-multi-sport.bat" del /q setup-multi-sport.bat
if exist "update-database.bat" del /q update-database.bat
if exist "upgrade-transfermarkt.bat" del /q upgrade-transfermarkt.bat

REM Gereksiz blade dosyalari
echo [5/5] Gereksiz view dosyalari siliniyor...
cd resources\views
if exist "welcome.blade.php" del /q welcome.blade.php
if exist "login.blade.php" del /q login.blade.php
if exist "live-scores.blade.php" del /q live-scores.blade.php
if exist "admin-dashboard.blade.php" del /q admin-dashboard.blade.php

cd /d e:\PhpstormProjects\untitled

echo.
echo ========================================
echo TEMIZLIK TAMAMLANDI!
echo ========================================
echo.
echo Silinen dosyalar:
echo - Eski HTML test sayfalari (18+)
echo - PDF klasorleri (3)
echo - Fazla dokumantasyon (25+)
echo - Setup scriptleri (4)
echo - Gereksiz view dosyalari (4)
echo.
echo Kalan onemli dosyalar:
echo - scout_api/ (Backend)
echo - NEXTSCOUT_HOMEPAGE.html (Test)
echo - FINAL_PROJECT_SUMMARY.md
echo - COMPLETE_API_ENDPOINTS.md
echo - DATABASE_SCHEMA_COMPLETE.md
echo - DEPLOYMENT_LAUNCH_GUIDE.md
echo.
echo ========================================
echo.

pause
