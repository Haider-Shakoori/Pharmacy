<?php

namespace App\Services\Tenancy;

use App\Contracts\Tenancy\TenantDatabaseProvisioner;
use App\Models\Tenant;
use App\Services\Cpanel\CpanelUapiClient;
use Illuminate\Support\Str;
use RuntimeException;

class CpanelTenantDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function __construct(
        private readonly CpanelUapiClient $cpanel,
    ) {}

    public function ensureDatabase(Tenant $tenant): void
    {
        $shortName = 'phm_'.Str::lower(Str::substr((string) $tenant->getTenantKey(), -16));
        $prefix = (string) config('pharmacy.cpanel.database_prefix');
        $fullName = $prefix.$shortName;
        $databaseUser = (string) config('pharmacy.cpanel.database_user');

        if ($databaseUser === '') {
            throw new RuntimeException('CPANEL_DATABASE_USER must identify the existing cPanel MySQL user used by Laravel.');
        }

        if (! $this->databaseExists($fullName)) {
            $this->cpanel->call('Mysql', 'create_database', ['name' => $fullName]);
        }

        $this->cpanel->call('Mysql', 'set_privileges_on_database', [
            'user' => $databaseUser,
            'database' => $fullName,
            'privileges' => 'ALL PRIVILEGES',
        ]);

        $tenant->setInternal('db_name', $fullName)->save();
    }

    private function databaseExists(string $database): bool
    {
        $result = $this->cpanel->call('Mysql', 'list_databases');

        return $this->containsExactDatabaseName($result['data'] ?? [], $database);
    }

    private function containsExactDatabaseName(mixed $value, string $database): bool
    {
        if (is_string($value)) {
            return hash_equals($database, $value);
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $key => $nested) {
            if (is_string($key) && hash_equals($database, $key)) {
                return true;
            }

            if ($this->containsExactDatabaseName($nested, $database)) {
                return true;
            }
        }

        return false;
    }
}
