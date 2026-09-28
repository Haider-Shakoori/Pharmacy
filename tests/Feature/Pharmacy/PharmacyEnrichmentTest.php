<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Demo\PharmacyDemoDataService;
use App\Services\Inventory\StockMovementService;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_demo_dataset_is_idempotent_and_populates_operational_modules(): void
    {
        $tenant = $this->createTenant(['name' => 'Demo Pharmacy', 'slug' => 'demo-pharmacy']);
        app(TrialProvisioner::class)->provision($tenant);
        app(RbacProvisioner::class)->provisionOwner($tenant, 'Demo Owner', 'owner@demo.test', 'password');

        $first = app(PharmacyDemoDataService::class)->seed($tenant);
        $second = app(PharmacyDemoDataService::class)->seed($tenant);

        $this->assertSame($first, $second);
        $tenant->run(function (): void {
            $this->assertDatabaseCount('medicines', 18);
            $this->assertDatabaseCount('customers', 8);
            $this->assertDatabaseCount('suppliers', 3);
            $this->assertDatabaseCount('sales', 8);
            $this->assertDatabaseCount('purchase_orders', 2);
            $this->assertDatabaseHas('product_batches', ['batch_key' => 'DEMO-MED-DEMO-001-EXPIRED']);
            $this->assertDatabaseHas('sales', ['prescription_reference' => 'RX-DEMO-003']);
        });
    }

    public function test_prescription_only_pos_sale_requires_reference_and_customer_module_is_available(): void
    {
        $tenant = $this->createTenant(['slug' => 'rx-pos']);
        app(TrialProvisioner::class)->provision($tenant);

        $tenant->run(function () use ($tenant): void {
            $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);
            $cashier = User::query()->create(['name' => 'Cashier', 'email' => 'cashier-rx@example.test', 'password' => 'password', 'is_active' => true]);
            $cashier->roles()->sync([$roles['cashier']->id]);
            $medicine = Medicine::query()->create(['medicine_code' => 'RX-001', 'brand_name' => 'Demo Antibiotic', 'sale_unit' => 'tablet', 'prescription_required' => true]);
            $branch = Branch::query()->create(['code' => 'RX', 'name' => 'RX Branch', 'is_default' => true, 'is_active' => true]);
            $location = StockLocation::query()->create(['branch_id' => $branch->id, 'code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true, 'is_active' => true]);
            $batch = ProductBatch::query()->create(['medicine_id' => $medicine->id, 'branch_id' => $branch->id, 'stock_location_id' => $location->id, 'batch_number' => 'RX-B1', 'batch_key' => 'RX-B1', 'expires_at' => today()->addYear(), 'status' => 'active', 'received_quantity' => 20, 'available_quantity' => 0, 'purchase_cost' => 10, 'sale_price' => 15]);
            app(StockMovementService::class)->record($batch, '20', 'receipt', 'test', 'rx', 'seed:rx', $cashier->id);
        });

        $host = $this->tenantHost($tenant);
        $this->onTenantDomain($tenant);
        $tenant->run(function () use ($host): void {
            $cashier = User::query()->where('email', 'cashier-rx@example.test')->firstOrFail();
            $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
            $medicine = Medicine::query()->where('medicine_code', 'RX-001')->firstOrFail();
            $base = ['stock_location_id' => $location->id, 'lines' => [['medicine_id' => $medicine->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 15]]];

            $this->actingAs($cashier)->withHeader('Host', $host)->get('/customers')->assertOk();
            $this->actingAs($cashier)->withHeader('Host', $host)->postJson('/pos/sales', $base + ['idempotency_key' => 'rx-missing'])->assertUnprocessable()->assertJsonValidationErrors('prescription_reference');
            $this->actingAs($cashier)->withHeader('Host', $host)->postJson('/pos/sales', $base + ['idempotency_key' => 'rx-ok', 'prescription_reference' => 'RX-TEST-001', 'prescriber_name' => 'Dr. Test', 'prescription_date' => today()->toDateString()])->assertCreated();
            $this->assertSame('RX-TEST-001', Sale::query()->where('idempotency_key', 'rx-ok')->value('prescription_reference'));
        });
    }
}
