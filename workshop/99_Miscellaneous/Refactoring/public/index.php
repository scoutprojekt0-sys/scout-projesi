<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/config.php';

$route = isset($_GET['r']) ? trim((string) $_GET['r']) : 'home';
$routes = app_routes();

if (!isset($routes[$route])) {
    app_response(404, 'Not found', array('Content-Type' => 'text/plain; charset=utf-8'));
}

$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
$routeConfig = $routes[$route];
$allowedMethods = isset($routeConfig['methods']) ? $routeConfig['methods'] : array('GET');
if (!in_array($method, $allowedMethods, true)) {
    app_response(405, 'Method not allowed', array(
        'Allow' => implode(', ', $allowedMethods),
        'Content-Type' => 'text/plain; charset=utf-8'
    ));
}

require_once $basePath . '/includes/session.php';

$requiresAuth = isset($routeConfig['auth']) ? (bool) $routeConfig['auth'] : false;
$allowedRoles = isset($routeConfig['roles']) && is_array($routeConfig['roles']) ? $routeConfig['roles'] : array();
if (!empty($allowedRoles)) {
    require_any_role($session, $allowedRoles);
} elseif ($requiresAuth) {
    require_auth($session);
}

require $basePath . '/' . $routeConfig['script'];
