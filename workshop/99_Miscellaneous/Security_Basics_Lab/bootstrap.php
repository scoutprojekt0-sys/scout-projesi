<?php
declare(strict_types=1);

$httpsEnabled = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_name('security_lab_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $httpsEnabled,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

$idleTimeoutSeconds = 30 * 60;
$now = time();

if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > $idleTimeoutSeconds) {
    session_unset();
    session_destroy();
    session_start();
}

$_SESSION['last_activity'] = $now;
