<?php

if ($argc < 2) {
    fwrite(STDERR, "Usage: php regression_quick.php <base_url> [username] [password]\n");
    fwrite(STDERR, "Example: php regression_quick.php http://localhost:8088 admin admin\n");
    exit(1);
}

$base = rtrim($argv[1], '/');
$loginUsername = $argc >= 3 ? (string) $argv[2] : 'admin';
$loginPassword = $argc >= 4 ? (string) $argv[3] : 'admin';

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] $message\n");
        exit(1);
    }
    fwrite(STDOUT, "[OK] $message\n");
}

function parse_status_code($statusLine)
{
    if (preg_match('/\s(\d{3})\s/', (string) $statusLine, $m)) {
        return (int) $m[1];
    }

    return 0;
}

function normalize_headers(array $rawHeaders)
{
    $headers = array();
    foreach ($rawHeaders as $header) {
        $parts = explode(':', $header, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $name = strtolower(trim($parts[0]));
        $value = trim($parts[1]);
        if (!isset($headers[$name])) {
            $headers[$name] = array();
        }
        $headers[$name][] = $value;
    }

    return $headers;
}

function extract_cookie_header(array $headers)
{
    if (!isset($headers['set-cookie'])) {
        return '';
    }

    $cookies = array();
    foreach ($headers['set-cookie'] as $cookieLine) {
        $firstPart = explode(';', $cookieLine, 2)[0];
        if (strpos($firstPart, '=') === false) {
            continue;
        }
        $cookies[] = trim($firstPart);
    }

    return implode('; ', $cookies);
}

function merge_cookie_headers($baseCookieHeader, $newCookieHeader)
{
    $cookieMap = array();

    foreach (array($baseCookieHeader, $newCookieHeader) as $cookieHeader) {
        $parts = explode(';', (string) $cookieHeader);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || strpos($part, '=') === false) {
                continue;
            }
            list($name, $value) = explode('=', $part, 2);
            $cookieMap[trim($name)] = trim($value);
        }
    }

    $merged = array();
    foreach ($cookieMap as $name => $value) {
        $merged[] = $name . '=' . $value;
    }

    return implode('; ', $merged);
}

function request_url($url, $method = 'GET', array $postData = array(), $cookieHeader = '')
{
    $headers = array('Accept: text/html');
    $content = '';

    if ($cookieHeader !== '') {
        $headers[] = 'Cookie: ' . $cookieHeader;
    }

    if (strtoupper($method) === 'POST') {
        $content = http_build_query($postData);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($content);
    }

    $context = stream_context_create(array(
        'http' => array(
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
        ),
    ));

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $body = '';
    }

    $rawHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : array();
    $status = count($rawHeaders) > 0 ? parse_status_code($rawHeaders[0]) : 0;
    $normalizedHeaders = normalize_headers($rawHeaders);

    return array(
        'status' => $status,
        'headers' => $normalizedHeaders,
        'body' => $body,
    );
}

function first_header_value(array $headers, $headerName)
{
    $key = strtolower($headerName);
    if (!isset($headers[$key]) || !isset($headers[$key][0])) {
        return '';
    }

    return (string) $headers[$key][0];
}

$home = request_url($base . '/index.php?r=home');
assert_true($home['status'] === 200, 'home route 200');
assert_true(strpos($home['body'], 'PhpStorm Simple CMS') !== false, 'home body contains site title');

$login = request_url($base . '/index.php?r=login');
assert_true($login['status'] === 200, 'login route 200');
assert_true(strpos($login['body'], 'Log on') !== false, 'login page renders form');

$cookie = extract_cookie_header($login['headers']);
assert_true($cookie !== '', 'login page sets session cookie');

$csrfToken = '';
if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $login['body'], $m)) {
    $csrfToken = $m[1];
}
assert_true($csrfToken !== '', 'login page exposes csrf token');

$csrfNegative = request_url($base . '/index.php?r=login', 'POST', array(
    'username' => $loginUsername,
    'password' => 'wrong-password',
), $cookie);
assert_true($csrfNegative['status'] === 419, 'login POST without csrf returns 419');

$invalidLogin = request_url($base . '/index.php?r=login', 'POST', array(
    'csrf_token' => $csrfToken,
    'username' => $loginUsername,
    'password' => 'wrong-password',
), $cookie);
assert_true($invalidLogin['status'] === 200, 'login POST with csrf returns 200 on invalid auth');
assert_true(strpos($invalidLogin['body'], 'Kullanici adi veya sifre hatali.') !== false, 'invalid auth message rendered');

$validLogin = request_url($base . '/index.php?r=login', 'POST', array(
    'csrf_token' => $csrfToken,
    'username' => $loginUsername,
    'password' => $loginPassword,
), $cookie);
assert_true($validLogin['status'] === 302, 'login success returns 302 redirect');
$loginLocation = first_header_value($validLogin['headers'], 'location');
assert_true(strpos($loginLocation, 'index.php?r=home') !== false, 'login success redirects to home');
$loginSetCookie = extract_cookie_header($validLogin['headers']);
$authCookie = merge_cookie_headers($cookie, $loginSetCookie);
assert_true($authCookie !== '', 'auth cookie available after successful login');

$protectedAfterLogin = request_url($base . '/index.php?r=page.add', 'GET', array(), $authCookie);
assert_true($protectedAfterLogin['status'] === 200, 'protected route opens after login');
assert_true(strpos($protectedAfterLogin['body'], 'Add Page') !== false, 'protected add page visible after login');

$logout = request_url($base . '/index.php?r=logout', 'GET', array(), $authCookie);
assert_true($logout['status'] === 302, 'logout returns redirect');
$logoutLocation = first_header_value($logout['headers'], 'location');
assert_true(strpos($logoutLocation, 'index.php?r=login') !== false, 'logout redirects to login');

$protectedAfterLogout = request_url($base . '/index.php?r=page.add', 'GET', array(), $authCookie);
assert_true($protectedAfterLogout['status'] === 302, 'protected route redirects after logout');
$protectedAfterLogoutLocation = first_header_value($protectedAfterLogout['headers'], 'location');
assert_true(strpos($protectedAfterLogoutLocation, 'index.php?r=login') !== false, 'logout invalidates access to protected route');

$protected = request_url($base . '/index.php?r=page.add');
assert_true(in_array($protected['status'], array(200, 302), true), 'protected route reachable (redirect or login view)');
if ($protected['status'] === 200) {
    assert_true(strpos($protected['body'], 'Log on') !== false || strpos($protected['body'], 'Add Page') !== false, 'protected route behavior expected');
}

fwrite(STDOUT, "\nRegression quick: PASS\n");
