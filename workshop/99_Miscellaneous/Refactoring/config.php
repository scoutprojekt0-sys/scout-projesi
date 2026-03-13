<?php

function load_local_env($path)
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env_value($key, $default = '')
{
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

if (!defined('SITE_PATH')) {
    define('SITE_PATH', dirname(realpath(__FILE__)));
    set_include_path(get_include_path() . PATH_SEPARATOR . SITE_PATH);
}

load_local_env(SITE_PATH . '/.env');

$environment = strtolower(env_value('APP_ENV', 'dev'));
$debugEnabled = in_array(strtolower(env_value('APP_DEBUG', $environment === 'prod' ? '0' : '1')), array('1', 'true', 'yes'), true);
$sessionSameSite = env_value('SESSION_COOKIE_SAMESITE', 'Lax');
if (!in_array($sessionSameSite, array('Lax', 'Strict', 'None'), true)) {
    $sessionSameSite = 'Lax';
}
if ($environment === 'prod') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
} else {
    ini_set('display_errors', $debugEnabled ? '1' : '0');
    ini_set('display_startup_errors', $debugEnabled ? '1' : '0');
    error_reporting(E_ALL);
}

require_once SITE_PATH . '/includes/app.php';

function required_env($key)
{
    $value = env_value($key, '');
    if ($value === '') {
        app_fail('Uygulama ayarlari eksik.', 'Missing required .env key: ' . $key);
    }

    return $value;
}

define('APP_ENV', $environment);
define('APP_DEBUG', $debugEnabled);
define('APP_LOG_FILE', SITE_PATH . '/logs/app.log');

define('DB_NAME', required_env('DB_NAME'));
define('DB_USER', required_env('DB_USER'));
define('DB_PASSWORD', env_value('DB_PASSWORD', ''));
define('DB_HOST', required_env('DB_HOST'));
define('DB_PORT', (int) required_env('DB_PORT'));
define('DB_CHARSET', required_env('DB_CHARSET'));

define('SESSION_TIMEOUT_SECONDS', (int) env_value('SESSION_TIMEOUT_SECONDS', '1800'));

define('SESSION_COOKIE_SAMESITE', $sessionSameSite);
define('ADMIN_ROLE_CACHE_TTL_SECONDS', (int) env_value('ADMIN_ROLE_CACHE_TTL_SECONDS', '120'));
define('LOGIN_RATE_LIMIT_WINDOW_SECONDS', (int) env_value('LOGIN_RATE_LIMIT_WINDOW_SECONDS', '900'));
define('LOGIN_RATE_LIMIT_MAX_ATTEMPTS', (int) env_value('LOGIN_RATE_LIMIT_MAX_ATTEMPTS', '5'));
define('LOGIN_RATE_LIMIT_LOCK_SECONDS', (int) env_value('LOGIN_RATE_LIMIT_LOCK_SECONDS', '900'));

define('DEFAULT_ADMIN_USERNAME', 'admin');
define('DEFAULT_ADMIN_PASSWORD', 'admin');

define('SITE_TITLE', 'PhpStorm Simple CMS');
