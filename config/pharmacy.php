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

    'offline' => [
        'mobile_first' => true,
        'default_license_grace_days' => 7,
    ],
];
