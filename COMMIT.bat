@echo off
REM Otomatik commit scripti
cd /d %~dp0
if not exist .git (
  echo Bu klasörde bir git deposu yok!
  pause
  exit /b 1
)
git add .
git commit -m "Giriş sistemi: Eski ve yeni kullanıcılar için local fallback login ve otomatik doğrulama alanı tamamlama (28 Mart 2026)"
git push
pause
