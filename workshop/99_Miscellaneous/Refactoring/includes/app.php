<?php

function app_response($statusCode, $body, array $headers = array())
{
    http_response_code($statusCode);
    foreach ($headers as $headerName => $headerValue) {
        header($headerName . ': ' . $headerValue);
    }
    echo $body;
    exit;
}

function app_redirect($path, $statusCode = 302)
{
    header('Location: ' . $path, true, $statusCode);
    exit;
}

function app_routes()
{
    return array(
        'home' => array('script' => 'index.php', 'methods' => array('GET')),
        'dashboard' => array('script' => 'dashboard.php', 'methods' => array('GET'), 'roles' => array('admin', 'editor')),
        'login' => array('script' => 'logon.php', 'methods' => array('GET', 'POST')),
        'register' => array('script' => 'register.php', 'methods' => array('GET', 'POST')),
        'logout' => array('script' => 'logoff.php', 'methods' => array('GET'), 'auth' => true),
        'password.change' => array('script' => 'changepassword.php', 'methods' => array('GET', 'POST'), 'auth' => true),
        'page.add' => array('script' => 'addpage.php', 'methods' => array('GET', 'POST'), 'roles' => array('admin', 'editor')),
        'page.edit.select' => array('script' => 'selectpagetoedit.php', 'methods' => array('GET', 'POST'), 'roles' => array('admin', 'editor')),
        'page.edit' => array('script' => 'editpage.php', 'methods' => array('GET', 'POST'), 'roles' => array('admin', 'editor')),
        'page.delete' => array('script' => 'deletepage.php', 'methods' => array('GET', 'POST'), 'roles' => array('admin'))
    );
}

function app_route_url($route, array $params = array())
{
    $routes = app_routes();
    if (!isset($routes[$route])) {
        app_fail('Gecersiz rota.', 'Unknown route requested in app_route_url: ' . (string) $route);
    }

    $query = http_build_query(array_merge(array('r' => $route), $params));
    return 'index.php' . ($query === '' ? '' : '?' . $query);
}

function app_redirect_route($route, array $params = array(), $statusCode = 302)
{
    app_redirect(app_route_url($route, $params), $statusCode);
}

function apply_security_headers()
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function normalize_password_hash($storedHash, $plainPassword)
{
    if ($storedHash === '' || $plainPassword === '') {
        return array(false, null);
    }

    if (strpos($storedHash, '$2y$') !== 0 && strpos($storedHash, '$argon2') !== 0) {
        return array(false, null);
    }

    $ok = password_verify($plainPassword, $storedHash);
    $rehash = null;
    if ($ok && password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
        $rehash = password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    return array($ok, $rehash);
}

 

function request_client_ip()
{
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return (string) $_SERVER['REMOTE_ADDR'];
    }
    return 'unknown';
}

function rate_limit_storage_file()
{
    return SITE_PATH . '/logs/login_attempts.json';
}

function load_rate_limit_data()
{
    $file = rate_limit_storage_file();
    if (!is_file($file)) {
        return array();
    }

    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return array();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return array();
    }

    return $decoded;
}

function save_rate_limit_data(array $data)
{
    $file = rate_limit_storage_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
}

function login_rate_limit_key($username)
{
    return strtolower(trim((string) $username)) . '|' . request_client_ip();
}

function login_rate_limit_is_blocked($username)
{
    $now = time();
    $key = login_rate_limit_key($username);
    $data = load_rate_limit_data();
    if (!isset($data[$key])) {
        return false;
    }

    $item = $data[$key];
    $lockedUntil = isset($item['locked_until']) ? (int) $item['locked_until'] : 0;
    if ($lockedUntil > $now) {
        return true;
    }

    return false;
}

function login_rate_limit_register_failure($username)
{
    $now = time();
    $key = login_rate_limit_key($username);
    $window = max(60, (int) LOGIN_RATE_LIMIT_WINDOW_SECONDS);
    $maxAttempts = max(3, (int) LOGIN_RATE_LIMIT_MAX_ATTEMPTS);
    $lockSeconds = max(60, (int) LOGIN_RATE_LIMIT_LOCK_SECONDS);

    $data = load_rate_limit_data();
    if (!isset($data[$key])) {
        $data[$key] = array('attempts' => array(), 'locked_until' => 0);
    }

    $attempts = array();
    foreach ((array) $data[$key]['attempts'] as $ts) {
        $ts = (int) $ts;
        if ($ts >= ($now - $window)) {
            $attempts[] = $ts;
        }
    }

    $attempts[] = $now;
    $data[$key]['attempts'] = $attempts;

    if (count($attempts) >= $maxAttempts) {
        $data[$key]['locked_until'] = $now + $lockSeconds;
        app_log('warning', 'Login blocked by rate limiter', array('key' => $key));
    }

    save_rate_limit_data($data);
}

function login_rate_limit_reset($username)
{
    $key = login_rate_limit_key($username);
    $data = load_rate_limit_data();
    if (isset($data[$key])) {
        unset($data[$key]);
        save_rate_limit_data($data);
    }
}
function app_log($level, $message, array $context = array())
{
    $date = date('c');
    $encodedContext = empty($context) ? '{}' : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encodedContext === false) {
        $encodedContext = '{}';
    }

    $line = sprintf("[%s] [%s] %s %s\n", $date, strtoupper((string) $level), (string) $message, $encodedContext);

    $baseLogPath = defined('APP_LOG_FILE') ? APP_LOG_FILE : (__DIR__ . '/../logs/app.log');
    $logDir = dirname($baseLogPath);
    $logFile = $logDir . '/app-' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    @file_put_contents($logFile, $line, FILE_APPEND);
}

function audit_log($eventType, $targetType = null, $targetId = null, array $meta = array())
{
    global $databaseConnection;

    if (!isset($databaseConnection) || !($databaseConnection instanceof mysqli)) {
        return false;
    }

    $eventType = trim((string) $eventType);
    if ($eventType === '') {
        return false;
    }

    $actorUserId = isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null;
    $actorUsername = isset($_SESSION['username']) ? (string) $_SESSION['username'] : null;
    $targetType = $targetType === null ? null : trim((string) $targetType);
    if ($targetType === '') {
        $targetType = null;
    }
    $targetId = $targetId === null ? null : (int) $targetId;

    $ipAddress = request_client_ip();
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    if (mb_strlen($userAgent) > 255) {
        $userAgent = mb_substr($userAgent, 0, 255);
    }

    $metaJson = null;
    if (!empty($meta)) {
        $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($metaJson === false) {
            $metaJson = '{}';
        }
    }

    $query = 'INSERT INTO audit_logs (event_type, actor_user_id, actor_username, target_type, target_id, ip_address, user_agent, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $statement = $databaseConnection->prepare($query);
    if (!$statement) {
        app_log('warning', 'Audit log prepare failed', array('event' => $eventType, 'db_error' => $databaseConnection->error));
        return false;
    }

    $statement->bind_param('sississs', $eventType, $actorUserId, $actorUsername, $targetType, $targetId, $ipAddress, $userAgent, $metaJson);
    $ok = $statement->execute();
    if (!$ok) {
        app_log('warning', 'Audit log execute failed', array('event' => $eventType, 'db_error' => $statement->error));
    }
    $statement->close();

    return $ok;
}

function app_fail($publicMessage, $technicalDetails = '')
{
    $requestId = date('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 6);

    if ($technicalDetails !== '') {
        app_log('error', $technicalDetails, array(
            'request_id' => $requestId,
            'uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : ''
        ));
    }

    $body = '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hata</title><style>body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}.box{max-width:640px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:12px}h1{margin:0 0 10px;font-size:22px}p{margin:8px 0;line-height:1.5}.id{font-family:monospace;color:#475569;background:#f1f5f9;padding:4px 6px;border-radius:6px;display:inline-block}</style></head><body><div class="box"><h1>Bir hata olustu</h1><p>' . e($publicMessage) . '</p><p>Devam edemezsen bu kodu paylas: <span class="id">' . e($requestId) . '</span></p></div></body></html>';

    app_response(500, $body, array('Content-Type' => 'text/html; charset=utf-8'));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function validate_csrf_or_fail()
{
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $expectedToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';

    if ($expectedToken === '' || $token === '' || !hash_equals($expectedToken, $token)) {
        app_log('warning', 'CSRF validation failed', array(
            'uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
        ));
        app_response(419, '<h1>Gecersiz istek</h1><p>Form guvenlik dogrulamasi basarisiz oldu.</p>', array('Content-Type' => 'text/html; charset=utf-8'));
    }
}

function render_error_summary(array $errors)
{
    if (empty($errors)) {
        return;
    }

    echo '<div class="message-error" role="alert" aria-live="assertive"><ul>';
    foreach ($errors as $error) {
        echo '<li>' . e($error) . '</li>';
    }
    echo '</ul></div>';
}

function post_value($key, $default = '')
{
    return isset($_POST[$key]) ? (string) $_POST[$key] : $default;
}
