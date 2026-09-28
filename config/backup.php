<?php

return [
    'root' => env(
        'BACKUP_ROOT',
        storage_path('app/private/backups'),
    ),

    'retention_count' => (int) env('BACKUP_RETENTION_COUNT', 14),

    'schedule_enabled' => (bool) env(
        'BACKUP_SCHEDULE_ENABLED',
        env('APP_ENV') === 'production',
    ),
    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:15'),
    'prune_time' => env('BACKUP_PRUNE_TIME', '03:15'),

    'process_timeout_seconds' => (int) env(
        'BACKUP_PROCESS_TIMEOUT_SECONDS',
        600,
    ),

    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('BACKUP_MYSQL_BINARY', 'mysql'),
];
