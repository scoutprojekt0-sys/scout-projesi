<?php

apply_security_headers();

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(array(
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => SESSION_COOKIE_SAMESITE,
));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['session_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = true;
}

$currentFingerprint = hash('sha256', (string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') . '|' . request_client_ip());
if (!isset($_SESSION['session_fingerprint'])) {
    $_SESSION['session_fingerprint'] = $currentFingerprint;
} elseif (!hash_equals((string) $_SESSION['session_fingerprint'], $currentFingerprint)) {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = true;
    $_SESSION['session_fingerprint'] = $currentFingerprint;
    app_log('warning', 'Session fingerprint mismatch');
}

$inactiveFor = isset($_SESSION['last_activity_at']) ? (time() - (int) $_SESSION['last_activity_at']) : 0;
if ($inactiveFor > SESSION_TIMEOUT_SECONDS) {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = true;
    $_SESSION['session_fingerprint'] = $currentFingerprint;
    app_log('info', 'Session timeout reached', array('timeout' => SESSION_TIMEOUT_SECONDS));
}
$_SESSION['last_activity_at'] = time();

require_once SITE_PATH . '/includes/connectDB.php';
require_once SITE_PATH . '/includes/classes/Session.php';

$sessionType = 'Session';
$session = new $sessionType($_SESSION);

function logged_on($session)
{
    return $session->has('userid');
}

function require_auth($session)
{
    if (!logged_on($session)) {
        app_redirect_route('login');
    }
}

function role_rank($role)
{
    $map = array(
        'user' => 10,
        'viewer' => 10,
        'editor' => 20,
        'admin' => 30,
    );

    $role = strtolower(trim((string) $role));
    return isset($map[$role]) ? $map[$role] : 0;
}

function current_user_roles($session)
{
    global $databaseConnection;

    if (!$session->has('userid')) {
        return array();
    }

    $userId = (int) $session->get('userid');
    $cacheTtl = defined('ADMIN_ROLE_CACHE_TTL_SECONDS') ? (int) ADMIN_ROLE_CACHE_TTL_SECONDS : 120;
    $cacheTtl = max(10, $cacheTtl);

    $cache = isset($_SESSION['role_cache']) && is_array($_SESSION['role_cache']) ? $_SESSION['role_cache'] : null;
    if ($cache !== null) {
        $cachedUserId = isset($cache['user_id']) ? (int) $cache['user_id'] : 0;
        $cachedUntil = isset($cache['valid_until']) ? (int) $cache['valid_until'] : 0;
        $cachedRoles = isset($cache['roles']) && is_array($cache['roles']) ? $cache['roles'] : array();
        if ($cachedUserId === $userId && $cachedUntil >= time()) {
            return $cachedRoles;
        }
    }

    $query = 'SELECT R.name FROM users_in_roles UIR INNER JOIN roles R ON UIR.role_id = R.id WHERE UIR.user_id = ?';
    $statement = $databaseConnection->prepare($query);
    $statement->bind_param('i', $userId);
    $statement->execute();
    $statement->bind_result($roleName);

    $roles = array();
    while ($statement->fetch()) {
        $roles[] = strtolower((string) $roleName);
    }
    $statement->close();

    if (empty($roles)) {
        $roles[] = 'viewer';
    }

    $_SESSION['role_cache'] = array(
        'user_id' => $userId,
        'roles' => $roles,
        'valid_until' => time() + $cacheTtl,
    );

    return $roles;
}

function has_role($session, $requiredRole)
{
    $requiredRank = role_rank($requiredRole);
    if ($requiredRank === 0) {
        return false;
    }

    $roles = current_user_roles($session);
    foreach ($roles as $role) {
        if (role_rank($role) >= $requiredRank) {
            return true;
        }
    }

    return false;
}

function has_any_role($session, array $roles)
{
    foreach ($roles as $role) {
        if (has_role($session, $role)) {
            return true;
        }
    }

    return false;
}

function require_role($session, $requiredRole)
{
    require_auth($session);
    if (!has_role($session, $requiredRole)) {
        app_redirect_route('home');
    }
}

function require_any_role($session, array $roles)
{
    require_auth($session);
    if (!has_any_role($session, $roles)) {
        app_redirect_route('home');
    }
}

function require_admin($session)
{
    require_role($session, 'admin');
}

function confirm_is_admin($session)
{
    require_admin($session);
}

function is_admin($session)
{
    return has_role($session, 'admin');
}
