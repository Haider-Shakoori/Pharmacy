<?php

namespace App\Console\Commands;

use App\Services\Backups\BackupManager;
use Illuminate\Console\Command;

class CreateBackup extends Command
{
    protected $signature = 'pharmacy:backup:create
        {--tenant=* : Limit backup to selected tenant ULID(s)}
        {--no-central : Skip the central SaaS database}
        {--central-only : Back up only the central SaaS database}';

    protected $description = 'Create verified central and tenant database snapshots.';

    public function handle(BackupManager $backups): int
    {
        if ($this->option('central-only') &&
            $this->option('no-central')) {
            $this->error(
                '--central-only and --no-central cannot be combined.',
            );

            return self::FAILURE;
        }

        $selected = array_values(
            array_filter(
                (array) $this->option('tenant'),
                fn ($value): bool => is_string($value) && $value !== '',
            ),
        );

        $tenantIds = $this->option('central-only')
            ? []
            : ($selected === [] ? null : $selected);

        try {
            $manifest = $backups->create(
                includeCentral: ! $this->option('no-central'),
                tenantIds: $tenantIds,
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup created: '.$manifest['name']);
        $this->table(
            ['Scope', 'Tenant', 'Driver', 'File', 'Bytes'],
            collect($manifest['entries'])
                ->map(fn (array $entry): array => [
                    $entry['scope'],
                    $entry['tenant_id'] ?? '—',
                    $entry['driver'],
                    $entry['file'],
                    $entry['bytes'],
                ])
                ->all(),
        );

        return self::SUCCESS;
    }
}
