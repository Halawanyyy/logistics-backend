<?php

$defaultOrigins = array_filter([
    env('FRONTEND_URL'),
    env('ADMIN_FRONTEND_URL'),
    env('MERCHANT_PORTAL_URL'),
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
]);

$configuredOrigins = array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
));

$configuredOriginPatterns = array_filter(array_map(
    static fn (string $pattern): string => trim($pattern),
    explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $configuredOrigins !== []
        ? array_values(array_unique($configuredOrigins))
        : array_values(array_unique($defaultOrigins)),

    'allowed_origins_patterns' => array_values($configuredOriginPatterns),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),
];
