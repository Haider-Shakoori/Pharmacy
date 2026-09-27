<?php

namespace Tests\Feature;

use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_root_redirects_to_platform(): void
    {
        $this->get('/')->assertRedirect('/platform');
    }

    public function test_tenant_dashboard_requires_login(): void
    {
        $tenant = $this->createTenant(['name' => 'Demo Pharmacy', 'slug' => 'demo']);

        $this->onTenantDomain($tenant)
            ->get('/')
            ->assertRedirect('/login');
    }

    public function test_authenticated_operational_owner_can_open_dashboard(): void
    {
        $this->withoutVite();

        $tenant = $this->createTenant(['name' => 'Demo Pharmacy', 'slug' => 'demo']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Demo Owner', 'owner@example.test', 'password123');

        $this->onTenantDomain($tenant)
            ->actingAs($owner)
            ->get('/')
            ->assertOk()
            ->assertSee('Demo Pharmacy')
            ->assertSee('Demo Owner');
    }
}
