<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_pharmacy_workspace(): void
    {
        $this->get('/')->assertRedirect('/pharmacy');
    }

    public function test_pharmacy_dashboard_requires_login(): void
    {
        $this->get('/pharmacy')->assertRedirect('/pharmacy/login');
    }

    public function test_authenticated_operational_owner_can_open_dashboard(): void
    {
        $this->withoutVite();

        $tenant = Tenant::query()->create(['name' => 'Demo Pharmacy', 'slug' => 'demo']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Demo Owner', 'owner@example.test', 'password123');

        $this->actingAs($owner)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/pharmacy')
            ->assertOk()
            ->assertSee('Demo Pharmacy')
            ->assertSee('Demo Owner');
    }
}
