<?php

namespace Tests\Feature\Tenancy;

use App\Services\Cpanel\CpanelUapiClient;
use App\Services\Tenancy\CpanelWildcardDomainProvisioner;
use Tests\TestCase;

class CpanelDomainProvisionerTest extends TestCase
{
    public function test_it_creates_a_direct_tenant_subdomain_and_requests_autossl(): void
    {
        config([
            'pharmacy.tenant_domain' => 'darmaltoon.com',
            'pharmacy.cpanel.tenant_document_root' => '/home/darmaltoon/pharmacy-app/public',
        ]);

        $cpanel = new class extends CpanelUapiClient
        {
            public array $calls = [];

            public function call(string $module, string $function, array $query = []): array
            {
                $this->calls[] = [$module, $function, $query];

                if ($module === 'DomainInfo' && $function === 'list_domains') {
                    return [
                        'status' => 1,
                        'data' => ['sub_domains' => []],
                    ];
                }

                return ['status' => 1, 'data' => null];
            }
        };

        (new CpanelWildcardDomainProvisioner($cpanel))
            ->ensureTlsRequested('test.darmaltoon.com');

        $this->assertSame([
            ['DomainInfo', 'list_domains', []],
            ['SubDomain', 'addsubdomain', [
                'domain' => 'test',
                'rootdomain' => 'darmaltoon.com',
                'dir' => '/home/darmaltoon/pharmacy-app/public',
            ]],
            ['SSL', 'start_autossl_check', []],
        ], $cpanel->calls);
    }

    public function test_it_does_not_recreate_an_existing_tenant_subdomain(): void
    {
        config([
            'pharmacy.tenant_domain' => 'darmaltoon.com',
            'pharmacy.cpanel.tenant_document_root' => '/home/darmaltoon/pharmacy-app/public',
        ]);

        $cpanel = new class extends CpanelUapiClient
        {
            public array $calls = [];

            public function call(string $module, string $function, array $query = []): array
            {
                $this->calls[] = [$module, $function, $query];

                if ($module === 'DomainInfo' && $function === 'list_domains') {
                    return [
                        'status' => 1,
                        'data' => ['sub_domains' => ['test.darmaltoon.com']],
                    ];
                }

                return ['status' => 1, 'data' => null];
            }
        };

        (new CpanelWildcardDomainProvisioner($cpanel))
            ->ensureTlsRequested('test.darmaltoon.com');

        $this->assertSame([
            ['DomainInfo', 'list_domains', []],
            ['SSL', 'start_autossl_check', []],
        ], $cpanel->calls);
    }
}
