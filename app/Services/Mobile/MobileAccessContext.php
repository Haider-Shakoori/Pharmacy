<?php

namespace App\Services\Mobile;

use App\Models\LicenseActivation;
use App\Models\Tenant;

final readonly class MobileAccessContext
{
    public function __construct(
        public Tenant $tenant,
        public LicenseActivation $activation,
        public int $userId,
        public array $user,
        public array $permissions,
    ) {}
}
