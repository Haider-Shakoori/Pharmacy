<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupManager;
use Illuminate\Console\Command;

class PruneBackups extends Command
{
    protected $signature = 'pharmacy:backup:prune
        {--keep= : Number of newest backups to retain}';

    protected $description = 'Prune old database backups by retention count.';

    public function handle(BackupManager $backups): int
    {
        $keep = $this->option('keep');
        $keep = is_numeric($keep) ? (int) $keep : null;

        try {
            $deleted = $backups->prune($keep);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(
            $deleted === []
                ? 'No backups required pruning.'
                : 'Pruned '.count($deleted).' backup(s).',
        );

        return self::SUCCESS;
    }
}
