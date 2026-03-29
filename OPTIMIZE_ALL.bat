@echo off
REM Laravel performans optimizasyon komutları toplu çalıştırılır

php artisan config:cache
php artisan route:cache
php artisan view:cache
php composer.phar dump-autoload -o

echo Tum optimizasyon komutlari calistirildi.
pause
