<?php

namespace App\Contracts\Tenancy;

interface TenantDomainProvisioner
{
    public function ensureTlsRequested(string $domain): void;
}
