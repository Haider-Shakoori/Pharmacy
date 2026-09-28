<?php

namespace App\Services\Tenancy;

use App\Contracts\Tenancy\TenantDatabaseProvisioner;
use App\Models\Tenant;
use Stancl\Tenancy\Jobs\CreateDatabase;

class LocalTenantDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function ensureDatabase(Tenant $tenant): void
    {
        $manager = $tenant->database()->manager();
        $name = (string) $tenant->database()->getName();

        if (! $manager->databaseExists($name)) {
            CreateDatabase::dispatchSync($tenant);
        }
    }
}
