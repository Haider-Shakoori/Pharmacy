<?php

namespace App\Services\Tenancy;

use App\Contracts\Tenancy\TenantDomainProvisioner;

class LocalTenantDomainProvisioner implements TenantDomainProvisioner
{
    public function ensureTlsRequested(string $domain): void
    {
        // Local/test domains do not require external TLS provisioning.
    }
}