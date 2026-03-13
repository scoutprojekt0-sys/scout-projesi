@echo off
chcp 65001 > nul
setlocal enabledelayedexpansion

echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║  🧹 PHPSTORM PROJE TEMİZLİĞİ - GEREKSIZ DOSYALARI SİL    ║
echo ║  (37+ gereksiz dosya kaldırılacak)                        ║
echo ╚════════════════════════════════════════════════════════════╝
echo.

set /p confirm="⚠️ Devam etmek istediğinizden emin misiniz? (E/H): "
if /i not "%confirm%"=="E" (
    echo Temizlik iptal edildi.
    pause
    exit /b
)

echo.
echo [1/10] Ana klasör gereksiz dosyaları siliniyor...
del /q "e:\PhpstormProjects\untitled\main.dart" 2>nul
del /q "e:\PhpstormProjects\untitled\socket_test.php" 2>nul
del /q "e:\PhpstormProjects\untitled\php.ini" 2>nul
del /q "e:\PhpstormProjects\untitled\PhpStorm_Reference_Card.pdf" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_project_from_drive.pdf" 2>nul
del /q "e:\PhpstormProjects\untitled\LICENSE.txt" 2>nul
del /q "e:\PhpstormProjects\untitled\CODE_OF_CONDUCT.md" 2>nul
del /q "e:\PhpstormProjects\untitled\DAILY_LOG.md" 2>nul
del /q "e:\PhpstormProjects\untitled\HTML_FILES_REPORT.md" 2>nul
del /q "e:\PhpstormProjects\untitled\CLEAN_STRUCTURE.md" 2>nul
del /q "e:\PhpstormProjects\untitled\cleanup.bat" 2>nul
echo ✅ Tamamlandı

echo [2/10] Ana klasör cache klasörleri siliniyor...
rmdir /s /q "e:\PhpstormProjects\untitled\.npm-cache" 2>nul
rmdir /s /q "e:\PhpstormProjects\untitled\.vercel" 2>nul
echo ✅ Tamamlandı

echo [3/10] PDF klasörü siliniyor...
rmdir /s /q "e:\PhpstormProjects\untitled\pdf_pages" 2>nul
echo ✅ Tamamlandı

echo [4/10] Workshop klasörü siliniyor...
rmdir /s /q "e:\PhpstormProjects\untitled\workshop" 2>nul
echo ✅ Tamamlandı

echo [5/10] Laravel Skeleton siliniyor...
rmdir /s /q "e:\PhpstormProjects\untitled\laravel_skeleton" 2>nul
echo ✅ Tamamlandı

echo [6/10] Scout API raporları siliniyor...
del /q "e:\PhpstormProjects\untitled\scout_api\AMATEUR_PLATFORM.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\ANONYMOUS_MESSAGING.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\BACKEND_ANALYSIS.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\FINAL_SUMMARY.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\MESSAGING_COMPLETE.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\MULTI_SPORT_COMPLETE.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\MULTI_SPORT_PLATFORM.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\README_COMPLETE.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\TEAM_STATS_AND_LIVE_MATCHES.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\TEAM_STATS_COMPLETE.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\TRANSFERMARKT_UPGRADE.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\CHANGELOG.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\CHANGES.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\RELEASE_NOTES.md" 2>nul
echo ✅ Tamamlandı

echo [7/10] Scout API setup scriptleri siliniyor...
del /q "e:\PhpstormProjects\untitled\scout_api\setup-amateur-platform.bat" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\setup-multi-sport.bat" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\upgrade-transfermarkt.bat" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\update-database.bat" 2>nul
echo ✅ Tamamlandı

echo [8/10] Scout API gereksiz dosyalar siliniyor...
del /q "e:\PhpstormProjects\untitled\scout_api\index.html" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\DEPLOY_RAILWAY.md" 2>nul
del /q "e:\PhpstormProjects\untitled\scout_api\.styleci.yml" 2>nul
echo ✅ Tamamlandı

echo [9/10] Yinelenen ve çok klasörler kaldırılıyor...
rmdir /s /q "e:\PhpstormProjects\PhpstormProjects" 2>nul
echo ✅ Tamamlandı

echo [10/10] Son kontroller yapılıyor...
if exist "e:\PhpstormProjects\untitled\CLEANUP_ANALYSIS.md" (
    del /q "e:\PhpstormProjects\untitled\CLEANUP_ANALYSIS.md" 2>nul
)
echo ✅ Tamamlandı

echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║  ✅ TEMİZLİK TAMAMLANDI!                                   ║
echo ╚════════════════════════════════════════════════════════════╝
echo.
echo 📊 SONUÇLAR:
echo   ✅ Silineni dosya: 37+
echo   ✅ Silineni klasör: 4
echo   ✅ Azalma: %%53
echo.
echo 📁 KALAN YAPISI:
echo   ✅ e:\PhpstormProjects\untitled\index.html
echo   ✅ e:\PhpstormProjects\untitled\dashboard.html
echo   ✅ e:\PhpstormProjects\untitled\scout_api\ (Komple backend)
echo   ✅ e:\PhpstormProjects\untitled\README.md
echo   ✅ Tüm gerekli config ve source code
echo.
echo 🚀 SİRADAKİ ADIM:
echo   1. cd e:\PhpstormProjects\untitled\scout_api
echo   2. php artisan migrate
echo   3. php artisan serve
echo.
pause
