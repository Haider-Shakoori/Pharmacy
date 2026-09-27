<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\SaleBatchAllocation;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPosTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_sale_allocates_fefo_stock_exactly_once(): void
    {
        $tenant = $this->createTenant(['slug' => 'pos-test']);

        $tenant->run(function () use ($tenant): void {
            $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);
            $cashier = User::query()->create(['name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password', 'is_active' => true]);
            $cashier->roles()->sync([$roles['cashier']->id]);
            $medicine = Medicine::query()->create(['medicine_code' => 'PARA-500', 'brand_name' => 'Paracetamol', 'strength' => '500 mg', 'sale_unit' => 'tablet']);
            $branch = Branch::query()->create(['code' => 'KBL', 'name' => 'Kabul', 'is_default' => true, 'is_active' => true]);
            $location = StockLocation::query()->create(['branch_id' => $branch->id, 'code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true, 'is_active' => true]);

            $early = ProductBatch::query()->create(['medicine_id' => $medicine->id, 'branch_id' => $branch->id, 'stock_location_id' => $location->id, 'batch_number' => 'EARLY', 'batch_key' => 'EARLY', 'expires_at' => today()->addMonth(), 'status' => 'active', 'received_quantity' => 5, 'available_quantity' => 0, 'purchase_cost' => 3, 'sale_price' => 5]);
            $later = ProductBatch::query()->create(['medicine_id' => $medicine->id, 'branch_id' => $branch->id, 'stock_location_id' => $location->id, 'batch_number' => 'LATER', 'batch_key' => 'LATER', 'expires_at' => today()->addMonths(6), 'status' => 'active', 'received_quantity' => 10, 'available_quantity' => 0, 'purchase_cost' => 3.5, 'sale_price' => 5]);

            app(StockMovementService::class)->record($early, '5', 'receipt', 'test', '1', 'seed:early', $cashier->id);
            app(StockMovementService::class)->record($later, '10', 'receipt', 'test', '2', 'seed:later', $cashier->id);
        });

        $host = $this->tenantHost($tenant);
        $this->onTenantDomain($tenant);

        $tenant->run(function () use ($host): void {
            $cashier = User::query()->where('email', 'cashier@example.test')->firstOrFail();
            $location = StockLocation::query()->where('code', 'MAIN')->firstOrFail();
            $medicine = Medicine::query()->where('medicine_code', 'PARA-500')->firstOrFail();
            $payload = [
                'stock_location_id' => $location->id,
                'idempotency_key' => 'pos-test-001',
                'lines' => [['medicine_id' => $medicine->id, 'quantity' => 7]],
                'payments' => [['method' => 'cash', 'amount' => 35]],
            ];

            $this->actingAs($cashier)->withHeader('Host', $host)->postJson('/pos/sales', $payload)->assertCreated();

            $this->assertDatabaseHas('sales', ['idempotency_key' => 'pos-test-001', 'status' => 'completed']);
            $this->assertDatabaseHas('product_batches', ['batch_number' => 'EARLY', 'available_quantity' => 0]);
            $this->assertDatabaseHas('product_batches', ['batch_number' => 'LATER', 'available_quantity' => 8]);
            $this->assertSame(2, SaleBatchAllocation::query()->count());
            $this->assertSame(2, StockMovement::query()->where('movement_type', 'sale')->count());

            $this->actingAs($cashier)->withHeader('Host', $host)->postJson('/pos/sales', $payload)->assertCreated();
            $this->assertSame(2, StockMovement::query()->where('movement_type', 'sale')->count());
        });
    }
}
