@echo off
color 0A
echo ============================================================
echo   NEXTSCOUT SMOKE TEST - Register, Login, Users List
echo ============================================================
echo.
echo Bu test su adimlari calistirir:
echo   1. API sunucusunun calistigini kontrol eder
echo   2. Kayit (register) endpoint test
echo   3. Giris (login) endpoint test  
echo   4. Users list endpoint test (role filter)
echo.
echo ============================================================
echo.

cd /d e:\PhpstormProjects\untitled\scout_api

echo [1/4] API Health Check...
curl -s -o nul -w "HTTP %%{http_code}" http://127.0.0.1:8000/api
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] API sunucusu calismiyor!
    echo Lutfen once: php artisan serve
    pause
    exit /b 1
)
echo  - OK
echo.

echo [2/4] Register Test (role: player)...
curl -X POST http://127.0.0.1:8000/api/auth/register ^
  -H "Content-Type: application/json" ^
  -H "Accept: application/json" ^
  -d "{\"name\":\"Test Player\",\"email\":\"test_player_%random%@test.com\",\"password\":\"123456\",\"role\":\"player\"}"
echo.
echo.

echo [3/4] Login Test...
curl -X POST http://127.0.0.1:8000/api/auth/login ^
  -H "Content-Type: application/json" ^
  -H "Accept: application/json" ^
  -d "{\"email\":\"test_player_1@test.com\",\"password\":\"123456\"}"
echo.
echo.

echo [4/4] Users List Test (role=player, per_page=5)...
curl -X GET "http://127.0.0.1:8000/api/users?role=player&per_page=5"
echo.
echo.

echo ============================================================
echo   SMOKE TEST TAMAMLANDI
echo ============================================================
echo.
echo Manuel test icin:
echo   - antranor-giris.html (role: coach)
echo   - menejer-giris.html (role: manager)
echo   - takim-giris.html (role: team)
echo   - admin/index.html (uyeler ve filtreleme)
echo.
pause
