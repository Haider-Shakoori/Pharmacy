<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'medicine_ocr' => [
        'endpoint' => env('MEDICINE_OCR_PROVIDER_ENDPOINT', ''),
        'driver' => env('MEDICINE_OCR_PROVIDER_DRIVER', 'custom'),
        'model' => env('MEDICINE_OCR_OPENAI_MODEL', 'gpt-4.1-mini'),
        'token' => env('MEDICINE_OCR_PROVIDER_TOKEN', ''),
        // Explicitly trusted DNS hostname(s); never accept arbitrary URLs.
        'allowed_hosts' => array_filter(array_map('trim', explode(',', (string) env('MEDICINE_OCR_ALLOWED_HOSTS', '')))),
    ],

];
