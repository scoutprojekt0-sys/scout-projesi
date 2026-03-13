@echo off
color 0E
echo.
echo ============================================================
echo   NEXTSCOUT PLATFORM - HIZLI BASLANGIC
echo ============================================================
echo.
echo   Tarih: 4 Mart 2026
echo   Durum: STABILIZASYON TAMAMLANDI
echo.
echo ============================================================
echo.
echo YAPMAK ISTEDIGINIZ ISLEMI SECIN:
echo.
echo   [1] API Sunucusunu Baslat
echo   [2] Smoke Test Calistir (Otomatik Test)
echo   [3] Ana Sayfayi Tarayicida Ac
echo   [4] Admin Panelini Tarayicida Ac
echo   [5] Tum Giris Sayfalarini Ac
echo   [6] Dokumantasyonu Goster
echo   [0] Cikis
echo.
echo ============================================================
echo.

set /p choice="Seciminiz (0-6): "

if "%choice%"=="1" goto start_api
if "%choice%"=="2" goto smoke_test
if "%choice%"=="3" goto open_index
if "%choice%"=="4" goto open_admin
if "%choice%"=="5" goto open_all
if "%choice%"=="6" goto show_docs
if "%choice%"=="0" goto end

echo Gecersiz secim!
pause
goto end

:start_api
echo.
echo ============================================================
echo API Sunucusu Baslatiliyor...
echo ============================================================
echo.
cd /d e:\PhpstormProjects\untitled
call start-server.bat
goto end

:smoke_test
echo.
echo ============================================================
echo Otomatik Test Baslatiliyor...
echo ============================================================
echo.
cd /d e:\PhpstormProjects\untitled
call SMOKE_TEST.bat
pause
goto end

:open_index
echo.
echo ============================================================
echo Ana Sayfa Aciliyor...
echo ============================================================
echo.
start "" "file:///E:/PhpstormProjects/untitled/index.html"
echo Tarayicinizda acildi!
timeout /t 2 >nul
goto end

:open_admin
echo.
echo ============================================================
echo Admin Paneli Aciliyor...
echo ============================================================
echo.
start "" "file:///E:/PhpstormProjects/untitled/admin/index.html"
echo Tarayicinizda acildi!
timeout /t 2 >nul
goto end

:open_all
echo.
echo ============================================================
echo Tum Giris Sayfalari Aciliyor...
echo ============================================================
echo.
start "" "file:///E:/PhpstormProjects/untitled/index.html"
start "" "file:///E:/PhpstormProjects/untitled/antranor-giris.html"
start "" "file:///E:/PhpstormProjects/untitled/menejer-giris.html"
start "" "file:///E:/PhpstormProjects/untitled/takim-giris.html"
start "" "file:///E:/PhpstormProjects/untitled/admin/index.html"
echo 5 sekme acildi!
timeout /t 3 >nul
goto end

:show_docs
echo.
echo ============================================================
echo DOKUMANTASYON DOSYALARI
echo ============================================================
echo.
echo   BASARILI_TAMAMLANDI.md      - Genel ozet ve durum
echo   CALISTIRMA_KILAVUZU.md      - Kullanim kilavuzu
echo   TESTLER.md                  - Test senaryolari
echo   GUNLUK_RAPOR.md             - Detayli rapor
echo.
echo ============================================================
echo.
set /p opendoc="Bir dosya acmak ister misiniz? (E/H): "
if /i "%opendoc%"=="E" (
    echo.
    echo   [1] BASARILI_TAMAMLANDI.md
    echo   [2] CALISTIRMA_KILAVUZU.md
    echo   [3] TESTLER.md
    echo   [4] GUNLUK_RAPOR.md
    echo.
    set /p docnum="Dosya numarasi: "
    if "!docnum!"=="1" start "" "BASARILI_TAMAMLANDI.md"
    if "!docnum!"=="2" start "" "CALISTIRMA_KILAVUZU.md"
    if "!docnum!"=="3" start "" "TESTLER.md"
    if "!docnum!"=="4" start "" "GUNLUK_RAPOR.md"
)
pause
goto end

:end
echo.
echo Cikiliyor...
timeout /t 1 >nul
exit
