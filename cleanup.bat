@echo off
chcp 65001 > nul
echo.
echo ╔════════════════════════════════════════════════════════╗
echo ║   🧹 PROJE DOSYA YAPISI TEMİZLİĞİ                    ║
echo ║   Gereksiz dosyaları siliyorum...                      ║
echo ╚════════════════════════════════════════════════════════╝
echo.

echo Siliniyor: Yinelenen index.html dosyaları...
REM Yinelenen klasörlerdeki dosyaları sil
rmdir /s /q "e:\PhpstormProjects\PhpstormProjects" 2>nul

echo Siliniyor: Gereksiz dashboard dosyaları...
del /s /q "e:\PhpstormProjects\untitled\workshop\99_Miscellaneous\Refactoring\dashboard.php" 2>nul

echo Siliniyor: Gereksiz admin_logs dosyaları...
del /s /q "e:\PhpstormProjects\untitled\workshop\99_Miscellaneous\Security_Basics_Lab\admin_logs.php" 2>nul

echo Siliniyor: Todo örneği...
rmdir /s /q "e:\PhpstormProjects\untitled\workshop\06_Todo" 2>nul

echo.
echo ╔════════════════════════════════════════════════════════╗
echo ║   ✅ TEMİZLİK TAMAMLANDI!                              ║
echo ╚════════════════════════════════════════════════════════╝
echo.
echo KALAN ÖNEMLİ DOSYALAR:
echo ✅ e:\PhpstormProjects\untitled\index.html
echo ✅ e:\PhpstormProjects\untitled\dashboard.html
echo ✅ e:\PhpstormProjects\untitled\scout_api\index.html
echo ✅ e:\PhpstormProjects\untitled\scout_api\dashboard.html
echo ✅ e:\PhpstormProjects\untitled\scout_api\resources\views\admin-dashboard.blade.php
echo.
pause
