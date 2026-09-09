<?php

return [
    'profiles_file' => env('SAAS_PROFILES_FILE'),
    'dedicated_tenant_id' => env('SAAS_DEDICATED_TENANT_ID'),
    'dedicated_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('SAAS_DEDICATED_HOSTS', ''))))),
    'inventory_path' => env('SAAS_INVENTORY_PATH', ''),
    'provision_username' => env('SAAS_PROVISION_DB_USERNAME'),
    'provision_password' => env('SAAS_PROVISION_DB_PASSWORD'),
    'integrations' => env('SAAS_INTEGRATIONS_FILE') ? require env('SAAS_INTEGRATIONS_FILE') : [],
    'enabled' => (bool) env('SAAS_ENABLED', false),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('SAAS_TRUSTED_PROXIES', ''))))),
    'base_domain' => env('SAAS_BASE_DOMAIN', 'sierra.test'),
    'platform_host' => env('SAAS_PLATFORM_HOST', 'admin.sierra.test'),
    'asset_key' => env('SAAS_ASSET_KEY'),
    'scheme' => env('SAAS_SCHEME', 'https'),
    'url_port' => env('SAAS_URL_PORT'),
    'storage_root' => env('SAAS_STORAGE_ROOT') ?: storage_path('tenants'),
    'central' => [
        'driver' => 'mysql', 'host' => env('SAAS_DB_HOST', '127.0.0.1'),
        'port' => env('SAAS_DB_PORT', '3306'), 'database' => env('SAAS_DB_DATABASE', 'sierra_platform'),
        'username' => env('SAAS_DB_USERNAME'), 'password' => env('SAAS_DB_PASSWORD'),
        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
    ],
    // Credentials are deployment secrets; the registry stores only the profile name.
    'profiles' => [
        'default' => [
            'driver' => 'mysql', 'host' => env('SAAS_TENANT_DB_HOST', '127.0.0.1'),
            'port' => env('SAAS_TENANT_DB_PORT', '3306'),
            'username' => env('SAAS_TENANT_DB_USERNAME'), 'password' => env('SAAS_TENANT_DB_PASSWORD'),
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ],
    ],
];
