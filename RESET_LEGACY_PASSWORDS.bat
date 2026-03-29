@echo off
REM Eski kullanıcıların şifrelerini sıfırlar ve eksik alanları günceller
composer dump-autoload
php artisan users:reset-legacy-passwords
pause
