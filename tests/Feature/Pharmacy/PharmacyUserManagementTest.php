<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_create_cashier_inside_own_tenant(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@example.test', 'password123');
        $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);

        $this->actingAs($owner)
            ->withSession(['tenant_id' => $tenant->id])
            ->post('/pharmacy/users', [
                'name' => 'Cashier One',
                'email' => 'cashier@example.test',
                'password' => 'password123',
                'is_active' => '1',
                'role_ids' => [$roles['cashier']->id],
            ])
            ->assertRedirect();

        $this->withSession(['tenant_id' => $tenant->id])
            ->get('/pharmacy/users')
            ->assertOk()
            ->assertSee('Cashier One');
    }
}
