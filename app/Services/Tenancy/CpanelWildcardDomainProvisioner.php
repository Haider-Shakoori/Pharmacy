<?php

namespace App\Services\Tenancy;

use App\Contracts\Tenancy\TenantDomainProvisioner;
use App\Services\Cpanel\CpanelUapiClient;

class CpanelWildcardDomainProvisioner implements TenantDomainProvisioner
{
    public function __construct(
        private readonly CpanelUapiClient $cpanel,
    ) {}

    public function ensureTlsRequested(string $domain): void
    {
        // Tenant DNS/vhost routing uses *.{deployment_host}, configured once on cPanel.
        // Trigger an account-level AutoSSL pass, then readiness is verified over HTTPS.
        $this->cpanel->call('SSL', 'start_autossl_check');
    }
}
