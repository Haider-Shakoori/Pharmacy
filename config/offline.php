<?php

return [
    'enabled' => env('APP_EDITION', 'cloud') === 'offline',
    'data_root' => env('OFFLINE_DATA_ROOT', storage_path('app/businessos/offline')),
    'activation_url' => env(
        'OFFLINE_ACTIVATION_URL',
        'https://pharmacy.businessos.af/api/v1/offline/license/activate',
    ),
    'http_timeout_seconds' => (int) env('OFFLINE_ACTIVATION_TIMEOUT_SECONDS', 20),
    'clock_rollback_tolerance_seconds' => (int) env('OFFLINE_CLOCK_ROLLBACK_TOLERANCE_SECONDS', 300),
    'machine_fingerprint_override' => env('OFFLINE_MACHINE_FINGERPRINT'),
    'app_version' => env('OFFLINE_APP_VERSION', 'dev'),
    'http_port' => (int) env('OFFLINE_HTTP_PORT', 8090),
];
