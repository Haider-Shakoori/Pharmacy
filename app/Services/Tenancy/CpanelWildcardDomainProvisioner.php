<?php

namespace App\Services\Tenancy;

use App\Contracts\Tenancy\TenantDomainProvisioner;
use App\Services\Cpanel\CpanelUapiClient;
use RuntimeException;

class CpanelWildcardDomainProvisioner implements TenantDomainProvisioner
{
    public function __construct(
        private readonly CpanelUapiClient $cpanel,
    ) {}

    public function ensureTlsRequested(string $domain): void
    {
        $tenantDomain = trim((string) config('pharmacy.tenant_domain'));
        $documentRoot = trim((string) config('pharmacy.cpanel.tenant_document_root'));

        if ($tenantDomain === '' || $documentRoot === '') {
            throw new RuntimeException('Tenant domain provisioning requires PHARMACY_TENANT_DOMAIN and CPANEL_TENANT_DOCUMENT_ROOT.');
        }

        $suffix = '.'.ltrim($tenantDomain, '.');

        if (! str_ends_with($domain, $suffix)) {
            throw new RuntimeException('Tenant domain does not match the configured tenant base domain.');
        }

        $subdomain = substr($domain, 0, -strlen($suffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            throw new RuntimeException('Tenant domain must be a direct subdomain of the configured tenant base domain.');
        }

        $listed = $this->cpanel->call('DomainInfo', 'list_domains');
        $subDomains = $listed['data']['sub_domains'] ?? [];

        if (! in_array($domain, is_array($subDomains) ? $subDomains : [], true)) {
            $this->cpanel->call('SubDomain', 'addsubdomain', [
                'domain' => $subdomain,
                'rootdomain' => $tenantDomain,
                'dir' => $documentRoot,
            ]);
        }

        $this->cpanel->call('SSL', 'start_autossl_check');
    }
}
