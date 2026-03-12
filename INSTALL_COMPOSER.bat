@echo off
echo ================================
echo Composer Installation Script
echo ================================
echo.
echo Downloading Composer installer...
echo.

cd /d %TEMP%

powershell -Command "& {Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile 'composer-setup.php'}"

if not exist composer-setup.php (
    echo ERROR: Failed to download Composer installer
    echo.
    echo Please manually download from: https://getcomposer.org/download/
    pause
    exit /b 1
)

echo.
echo Installing Composer...
php composer-setup.php --install-dir=%USERPROFILE%\AppData\Local\Bin --filename=composer

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Installation failed. Trying alternative method...
    php composer-setup.php
    if exist composer.phar (
        move composer.phar c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean\
        echo Composer installed to project directory
        echo Use: php composer.phar install
    )
    pause
    exit /b 0
)

del composer-setup.php

echo.
echo ================================
echo Composer installed successfully!
echo ================================
echo.
echo Now run: composer install
echo Or run: SETUP_COMPLETE.bat
echo.
pause
