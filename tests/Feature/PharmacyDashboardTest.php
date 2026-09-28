<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use App\Support\Tenancy\TenantContext;
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

    public function test_dashboard_and_alert_center_show_live_inventory_alerts(): void
    {
        $this->withoutVite();

        $tenant = $this->createTenant(['name' => 'Alert Pharmacy', 'slug' => 'alerts']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner(
            $tenant,
            'Alert Owner',
            'alerts@example.test',
            'password123',
        );

        $location = app(InventoryProvisioner::class)->ensureDefaults($tenant);

        app(TenantContext::class)->run($tenant, function () use ($location): void {
            $medicine = Medicine::query()->create([
                'medicine_code' => 'ALERT-001',
                'brand_name' => 'Alert Medicine',
                'sale_unit' => 'box',
                'reorder_level' => 10,
            ]);

            ProductBatch::query()->create([
                'medicine_id' => $medicine->id,
                'branch_id' => $location->branch_id,
                'stock_location_id' => $location->id,
                'batch_number' => 'LOW-LOT',
                'batch_key' => 'LOW-LOT',
                'expires_at' => today()->addDays(20),
                'status' => 'active',
                'received_quantity' => 5,
                'available_quantity' => 5,
                'purchase_cost' => 1,
                'sale_price' => 2,
            ]);

            ProductBatch::query()->create([
                'medicine_id' => $medicine->id,
                'branch_id' => $location->branch_id,
                'stock_location_id' => $location->id,
                'batch_number' => 'EXPIRED-LOT',
                'batch_key' => 'EXPIRED-LOT',
                'expires_at' => today()->subDay(),
                'status' => 'active',
                'received_quantity' => 2,
                'available_quantity' => 2,
                'purchase_cost' => 1,
                'sale_price' => 2,
            ]);
        });

        $this->onTenantDomain($tenant)
            ->actingAs($owner)
            ->get('/')
            ->assertOk()
            ->assertSee('View alerts');

        $this->onTenantDomain($tenant)
            ->actingAs($owner)
            ->get('/alerts')
            ->assertOk()
            ->assertSee('Alert Medicine')
            ->assertSee('LOW-LOT')
            ->assertSee('EXPIRED-LOT');
    }
}
