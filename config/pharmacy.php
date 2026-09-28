<?php

return [
    'deployment_host' => env('PHARMACY_DEPLOYMENT_HOST', 'pharmacy.businessos.af'),
    'currency' => env('PHARMACY_CURRENCY', 'AFN'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Kabul'),
    'locales' => ['en', 'fa', 'ps'],
    'rtl_locales' => ['fa', 'ps'],

    'performance' => [
        'default_page_size' => 25,
        'max_page_size' => 100,
        'sync_page_size' => 100,
        'sync_max_page_size' => 250,
        'server_side_search' => true,
        'delta_sync' => true,
        'external_fonts' => false,
    ],

    'security' => [
        'hsts_enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'max_api_payload_bytes' => (int) env('SECURITY_MAX_API_PAYLOAD_BYTES', 262144),
        'max_signed_token_bytes' => (int) env('SECURITY_MAX_SIGNED_TOKEN_BYTES', 8192),
        'token_clock_skew_seconds' => (int) env('SECURITY_TOKEN_CLOCK_SKEW_SECONDS', 300),
    ],

    'trial' => [
        'days' => 7,
    ],

    'offline' => [
        'mobile_first' => true,
        'default_license_grace_days' => 7,
        'max_installations_per_license' => (int) env('OFFLINE_MAX_INSTALLATIONS_PER_LICENSE', 1),
    ],

    'mobile' => [
        'android_download_url' => env('PHARMACY_ANDROID_APK_URL', '/downloads/businessos-pharmacy.apk'),
    ],

    'license' => [
        'key_prefix' => 'PHM',
        'signing_private_key' => env('LICENSE_SIGNING_PRIVATE_KEY_B64'),
        'signing_public_key' => env('LICENSE_SIGNING_PUBLIC_KEY_B64'),
    ],

    'provisioning' => [
        'driver' => env('TENANCY_DB_PROVISIONER', 'local'),
        'readiness_timeout_seconds' => (int) env('TENANT_READINESS_TIMEOUT_SECONDS', 8),
    ],

    'cpanel' => [
        'host' => env('CPANEL_API_HOST'),
        'username' => env('CPANEL_USERNAME'),
        'api_token' => env('CPANEL_API_TOKEN'),
        'database_prefix' => env('CPANEL_DATABASE_PREFIX', env('CPANEL_USERNAME') ? env('CPANEL_USERNAME').'_' : ''),
        'database_user' => env('CPANEL_DATABASE_USER'),
        'timeout_seconds' => (int) env('CPANEL_API_TIMEOUT_SECONDS', 15),
        'verify_tls' => (bool) env('CPANEL_VERIFY_TLS', true),
        'tenant_domain_mode' => 'wildcard',
    ],

    'platform' => [
        'bootstrap_admin' => [
            'name' => env('PLATFORM_ADMIN_NAME', 'Platform Administrator'),
            'email' => env('PLATFORM_ADMIN_EMAIL'),
            'password' => env('PLATFORM_ADMIN_PASSWORD'),
        ],
    ],
];
