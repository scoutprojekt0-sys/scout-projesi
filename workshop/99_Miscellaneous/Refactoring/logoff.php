<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/session.php';

if (isset($_SESSION['userid'])) {
    audit_log('logout', 'user', (int) $_SESSION['userid']);
}

$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

app_redirect_route('login');
