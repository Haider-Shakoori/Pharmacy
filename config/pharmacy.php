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
        'server_side_search' => true,
        'delta_sync' => true,
        'external_fonts' => false,
    ],

    'trial' => [
        'days' => 7,
    ],

    'offline' => [
        'mobile_first' => true,
        'default_license_grace_days' => 7,
    ],

    'license' => [
        'key_prefix' => 'PHM',
        'signing_private_key' => env('LICENSE_SIGNING_PRIVATE_KEY_B64'),
        'signing_public_key' => env('LICENSE_SIGNING_PUBLIC_KEY_B64'),
    ],

    'platform' => [
        'bootstrap_admin' => [
            'name' => env('PLATFORM_ADMIN_NAME', 'Platform Administrator'),
            'email' => env('PLATFORM_ADMIN_EMAIL'),
            'password' => env('PLATFORM_ADMIN_PASSWORD'),
        ],
    ],
];
