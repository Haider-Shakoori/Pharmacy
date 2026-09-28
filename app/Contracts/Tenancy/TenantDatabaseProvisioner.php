<?php

namespace App\Contracts\Tenancy;

use App\Models\Tenant;

interface TenantDatabaseProvisioner
{
    public function ensureDatabase(Tenant $tenant): void;
}