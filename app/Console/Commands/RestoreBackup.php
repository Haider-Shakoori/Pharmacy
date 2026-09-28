<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupManager;
use Illuminate\Console\Command;

class RestoreBackup extends Command
{
    protected $signature = 'pharmacy:backup:restore
        {backup : Backup directory name}
        {--central : Restore the central SaaS database}
        {--tenant= : Restore one tenant database by ULID}
        {--force : Confirm destructive restore}';

    protected $description = 'Restore one verified database snapshot.';

    public function handle(BackupManager $backups): int
    {
        $central = (bool) $this->option('central');
        $tenant = $this->option('tenant');

        if ($central === (is_string($tenant) && $tenant !== '')) {
            $this->error(
                'Choose exactly one of --central or --tenant=<ULID>.',
            );

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error(
                'Restore is destructive. Re-run with --force.',
            );

            return self::FAILURE;
        }

        if (! app()->isDownForMaintenance()) {
            $this->error(
                'Put the application in maintenance mode first: php artisan down',
            );

            return self::FAILURE;
        }

        try {
            $entry = $central
                ? $backups->restoreCentral(
                    (string) $this->argument('backup'),
                )
                : $backups->restoreTenant(
                    (string) $this->argument('backup'),
                    (string) $tenant,
                );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(
            'Restore completed from '.$entry['file'].'.',
        );

        return self::SUCCESS;
    }
}
