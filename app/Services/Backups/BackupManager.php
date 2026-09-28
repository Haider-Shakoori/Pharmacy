<?php

namespace App\Services\Backups;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BackupManager
{
    public function __construct(
        private readonly DatabaseSnapshotService $snapshots,
    ) {}

    public function create(
        bool $includeCentral = true,
        ?array $tenantIds = null,
    ): array {
        if (! $includeCentral && $tenantIds === []) {
            throw new RuntimeException(
                'Backup selection contains no databases.',
            );
        }

        $name = now()
            ->utc()
            ->format('Ymd_His')
            .'_'
            .Str::lower(Str::random(6));
        $directory = $this->root().DIRECTORY_SEPARATOR.$name;

        File::ensureDirectoryExists($directory, 0700, true);

        $manifest = [
            'version' => 1,
            'name' => $name,
            'created_at' => now()->utc()->toIso8601String(),
            'application' => (string) config(
                'app.name',
                'BusinessOS Pharmacy',
            ),
            'license_public_key_fingerprint' => hash(
                'sha256',
                (string) config(
                    'pharmacy.license.signing_public_key',
                    '',
                ),
            ),
            'entries' => [],
        ];

        try {
            if ($includeCentral) {
                $manifest['entries'][] = $this->backupConnection(
                    'central',
                    DB::connection('central')->getConfig(),
                    $directory,
                    'central',
                );
            }

            $tenants = Tenant::query()
                ->with('business')
                ->when(
                    is_array($tenantIds),
                    fn ($query) => $query->whereKey($tenantIds),
                )
                ->orderBy('id')
                ->get();

            if (is_array($tenantIds) &&
                $tenants->count() !== count(array_unique($tenantIds))) {
                throw new RuntimeException(
                    'One or more requested tenants were not found.',
                );
            }

            foreach ($tenants as $tenant) {
                $entry = $tenant->run(
                    function () use ($tenant, $directory): array {
                        return $this->backupConnection(
                            'tenant',
                            DB::connection('tenant')->getConfig(),
                            $directory,
                            'tenant-'.$tenant->id,
                            (string) $tenant->id,
                            $tenant->business?->slug,
                        );
                    },
                );

                $manifest['entries'][] = $entry;
            }

            $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';

            if (file_put_contents(
                $manifestPath,
                json_encode(
                    $manifest,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR,
                ),
                LOCK_EX,
            ) === false) {
                throw new RuntimeException(
                    'Unable to write backup manifest.',
                );
            }

            @chmod($manifestPath, 0600);

            return $manifest;
        } catch (\Throwable $exception) {
            File::deleteDirectory($directory);

            throw $exception;
        }
    }

    public function verify(string $backup): array
    {
        [$directory, $manifest] = $this->loadManifest($backup);

        foreach ($manifest['entries'] as $entry) {
            $path = $this->entryPath($directory, $entry);

            if (! is_file($path)) {
                throw new RuntimeException(
                    'Backup file is missing: '.$entry['file'],
                );
            }

            if (! hash_equals(
                (string) $entry['sha256'],
                hash_file('sha256', $path),
            )) {
                throw new RuntimeException(
                    'Backup checksum mismatch: '.$entry['file'],
                );
            }

            if ((int) $entry['bytes'] !== filesize($path)) {
                throw new RuntimeException(
                    'Backup size mismatch: '.$entry['file'],
                );
            }

            if (($entry['driver'] ?? null) === 'sqlite' &&
                ! $this->snapshots->integrityCheck($path)) {
                throw new RuntimeException(
                    'SQLite integrity check failed: '.$entry['file'],
                );
            }
        }

        $manifest['verified'] = true;

        return $manifest;
    }

    public function restoreCentral(string $backup): array
    {
        [$directory, $manifest] = $this->loadManifest($backup);
        $this->verify($backup);

        $entry = collect($manifest['entries'])
            ->firstWhere('scope', 'central');

        if (! is_array($entry)) {
            throw new RuntimeException(
                'Backup does not contain a central database snapshot.',
            );
        }

        $config = DB::connection('central')->getConfig();
        $this->assertDriverMatches($entry, $config);

        $this->snapshots->restore(
            'central',
            $config,
            $this->entryPath($directory, $entry),
        );

        return $entry;
    }

    public function restoreTenant(
        string $backup,
        string $tenantId,
    ): array {
        [$directory, $manifest] = $this->loadManifest($backup);
        $this->verify($backup);

        $entry = collect($manifest['entries'])->first(
            fn (array $entry): bool =>
                ($entry['scope'] ?? null) === 'tenant'
                && (string) ($entry['tenant_id'] ?? '') === $tenantId,
        );

        if (! is_array($entry)) {
            throw new RuntimeException(
                'Backup does not contain the requested tenant snapshot.',
            );
        }

        $tenant = Tenant::query()->findOrFail($tenantId);

        $tenant->run(function () use ($directory, $entry): void {
            $config = DB::connection('tenant')->getConfig();
            $this->assertDriverMatches($entry, $config);

            $this->snapshots->restore(
                'tenant',
                $config,
                $this->entryPath($directory, $entry),
            );
        });

        return $entry;
    }

    public function list(): array
    {
        $backups = [];

        foreach (File::directories($this->root()) as $directory) {
            $manifest = $directory.DIRECTORY_SEPARATOR.'manifest.json';

            if (! is_file($manifest)) {
                continue;
            }

            try {
                $data = json_decode(
                    (string) file_get_contents($manifest),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            } catch (\Throwable) {
                continue;
            }

            if (is_array($data)) {
                $backups[] = $data;
            }
        }

        usort(
            $backups,
            fn (array $left, array $right): int =>
                strcmp(
                    (string) $right['created_at'],
                    (string) $left['created_at'],
                ),
        );

        return $backups;
    }

    public function prune(?int $keep = null): array
    {
        $keep ??= (int) config('backup.retention_count', 14);
        $keep = max(1, $keep);

        $backups = $this->list();
        $deleted = [];

        foreach (array_slice($backups, $keep) as $backup) {
            $name = (string) ($backup['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $directory = $this->resolve($name);
            File::deleteDirectory($directory);
            $deleted[] = $name;
        }

        return $deleted;
    }

    private function backupConnection(
        string $connectionName,
        array $config,
        string $directory,
        string $basename,
        ?string $tenantId = null,
        ?string $tenantSlug = null,
    ): array {
        $driver = (string) ($config['driver'] ?? '');
        $extension = $this->snapshots->extension($config);
        $filename = $basename.'.'.$extension;
        $destination = $directory.DIRECTORY_SEPARATOR.$filename;

        $this->snapshots->backup(
            $connectionName,
            $config,
            $destination,
        );

        return [
            'scope' => $tenantId === null ? 'central' : 'tenant',
            'tenant_id' => $tenantId,
            'tenant_slug' => $tenantSlug,
            'driver' => $driver,
            'database' => basename(
                (string) ($config['database'] ?? ''),
            ),
            'file' => $filename,
            'sha256' => hash_file('sha256', $destination),
            'bytes' => filesize($destination),
        ];
    }

    private function loadManifest(string $backup): array
    {
        $directory = $this->resolve($backup);
        $path = $directory.DIRECTORY_SEPARATOR.'manifest.json';

        if (! is_file($path)) {
            throw new RuntimeException(
                'Backup manifest was not found.',
            );
        }

        $manifest = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (! is_array($manifest) ||
            ($manifest['version'] ?? null) !== 1 ||
            ! is_array($manifest['entries'] ?? null)) {
            throw new RuntimeException(
                'Backup manifest is invalid.',
            );
        }

        return [$directory, $manifest];
    }

    private function resolve(string $backup): string
    {
        if ($backup === '' || basename($backup) !== $backup) {
            throw new RuntimeException(
                'Backup name is invalid.',
            );
        }

        $directory = $this->root().DIRECTORY_SEPARATOR.$backup;

        if (! is_dir($directory)) {
            throw new RuntimeException(
                'Backup does not exist: '.$backup,
            );
        }

        return $directory;
    }

    private function entryPath(
        string $directory,
        array $entry,
    ): string {
        $file = (string) ($entry['file'] ?? '');

        if ($file === '' || basename($file) !== $file) {
            throw new RuntimeException(
                'Backup manifest contains an unsafe file path.',
            );
        }

        return $directory.DIRECTORY_SEPARATOR.$file;
    }

    private function assertDriverMatches(
        array $entry,
        array $config,
    ): void {
        if ((string) ($entry['driver'] ?? '') !==
            (string) ($config['driver'] ?? '')) {
            throw new RuntimeException(
                'Backup driver does not match the target database.',
            );
        }
    }

    private function root(): string
    {
        $root = (string) config(
            'backup.root',
            storage_path('app/private/backups'),
        );

        File::ensureDirectoryExists($root, 0700, true);

        return rtrim($root, DIRECTORY_SEPARATOR);
    }
}
