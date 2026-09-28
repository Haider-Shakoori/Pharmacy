<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupManager;
use Illuminate\Console\Command;

class VerifyBackup extends Command
{
    protected $signature = 'pharmacy:backup:verify
        {backup : Backup directory name}';

    protected $description = 'Verify backup checksums and SQLite integrity.';

    public function handle(BackupManager $backups): int
    {
        try {
            $manifest = $backups->verify(
                (string) $this->argument('backup'),
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(
            'Backup verified: '.$manifest['name']
            .' ('.count($manifest['entries']).' database(s))',
        );

        return self::SUCCESS;
    }
}
