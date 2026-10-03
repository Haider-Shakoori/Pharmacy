<?php

namespace App\Services\Sync;

use App\Models\LicenseActivation;
use App\Models\Tenant;

readonly class SyncAccessContext
{
    public function __construct(
        public Tenant $tenant,
        public LicenseActivation $activation,
        public int $userId,
        public array $user,
        public array $permissions,
    ) {}
}
