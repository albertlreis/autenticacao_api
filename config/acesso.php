<?php

return [
    'access_token_ttl_minutes' => (int) env('ACCESS_TOKEN_TTL_MINUTES', 15),
    'refresh_token_ttl_days'   => (int) env('REFRESH_TOKEN_TTL_DAYS', 30),

    'refresh_cookie' => [
        'name'      => env('REFRESH_COOKIE_NAME', 'refresh_token'),
        'domain'    => env('REFRESH_COOKIE_DOMAIN') ?: null,
        'secure'    => (bool) env('REFRESH_COOKIE_SECURE', true),
        'same_site' => env('REFRESH_COOKIE_SAMESITE', 'None'),
        'path'      => '/',
    ],

    'permissions_cache_ttl_hours' => (int) env('PERMISSIONS_CACHE_TTL_HOURS', 6),

    'allowed_origins' => array_values(array_filter(array_map(
        static fn ($v) => trim($v),
        explode(',', (string) env('AUTH_ALLOWED_ORIGINS', ''))
    ))),
];
