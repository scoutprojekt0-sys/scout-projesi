<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    'cors' => [
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))
        ))),
    ],

    'auth' => [
        'token_expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 10080),
    ],

    'performance' => [
        'opportunities_cache_enabled' => (bool) env('OPPORTUNITIES_CACHE_ENABLED', true),
        'opportunities_cache_ttl_seconds' => (int) env('OPPORTUNITIES_CACHE_TTL_SECONDS', 60),
    ],

    'rate_limits' => [
        'auth_per_minute' => (int) env('RATE_LIMIT_AUTH_PER_MINUTE', 5),
        'api_read_per_minute' => (int) env('RATE_LIMIT_API_READ_PER_MINUTE', 120),
        'api_write_per_minute' => (int) env('RATE_LIMIT_API_WRITE_PER_MINUTE', 40),
        'auth_failed_attempts_before_lock' => (int) env('AUTH_FAILED_ATTEMPTS_BEFORE_LOCK', 5),
        'auth_lock_seconds' => (int) env('AUTH_LOCK_SECONDS', 900),
    ],

    'logging' => [
        'security_level' => env('LOG_SECURITY_LEVEL', 'info'),
        'security_days' => (int) env('LOG_SECURITY_DAYS', 30),
        'ops_level' => env('LOG_OPS_LEVEL', 'info'),
        'ops_days' => (int) env('LOG_OPS_DAYS', 14),
    ],

    'monitoring' => [
        'slow_request_ms' => (int) env('MONITOR_SLOW_REQUEST_MS', 800),
    ],
];
