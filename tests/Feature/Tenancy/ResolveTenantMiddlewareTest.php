<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_is_identified_from_its_domain(): void
    {
        $tenant = $this->createTenant(['name' => 'A Pharmacy', 'slug' => 'a-pharmacy']);

        $this->withoutVite();
        $this->onTenantDomain($tenant)
            ->get('/login')
            ->assertOk()
            ->assertSee('Pharmacy sign in');

        $this->assertSame($tenant->id, tenant('id'));
    }

    public function test_unknown_tenant_domain_is_rejected(): void
    {
        $this->get('https://unknown.'.config('pharmacy.deployment_host').'/login')
            ->assertNotFound();
    }

    public function test_central_domain_cannot_access_tenant_routes(): void
    {
        $this->get('https://'.config('tenancy.central_domains.0').'/login')->assertNotFound();
    }
}
