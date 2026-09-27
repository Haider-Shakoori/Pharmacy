<?php

namespace Tests\Feature\Pharmacy;

use App\Models\GoodsReceipt;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\FefoAllocator;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Inventory\PostGoodsReceiptToInventory;
use App\Services\Inventory\StockMovementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_tables_are_tenant_only_and_defaults_are_provisioned(): void
    {
        $tenant = $this->createTenant(['name' => 'Inventory Pharmacy', 'slug' => 'inventory-pharmacy']);
        $location = app(InventoryProvisioner::class)->ensureDefaults($tenant);

        $this->assertFalse(Schema::connection('central')->hasTable('product_batches'));
        app(TenantContext::class)->run($tenant, function () use ($location): void {
            $this->assertTrue(Schema::hasTable('product_batches'));
            $this->assertSame('MAIN', $location->branch->code);
            $this->assertSame('MAIN', $location->code);
            $this->assertTrue($location->is_default);
        });
    }

    public function test_goods_receipt_posts_batch_and_movement_exactly_once_with_bonus_costing(): void
    {
        $tenant = $this->createTenant(['name' => 'Cost Pharmacy', 'slug' => 'cost-pharmacy']);
        $location = app(InventoryProvisioner::class)->ensureDefaults($tenant);

        app(TenantContext::class)->run($tenant, function () use ($location): void {
            $user = User::query()->create([
                'name' => 'Inventory User',
                'email' => 'inventory@example.test',
                'password' => 'password123',
            ]);
            $supplier = Supplier::query()->create(['code' => 'SUP-COST', 'name' => 'Cost Supplier']);
            $medicine = Medicine::query()->create([
                'medicine_code' => 'MED-COST',
                'brand_name' => 'Cost Medicine',
                'purchase_unit' => 'box',
                'sale_unit' => 'tablet',
                'units_per_purchase_unit' => 10,
                'reorder_level' => 5,
            ]);
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $supplier->id,
                'number' => 'PO-COST-001',
                'status' => 'approved',
                'order_date' => now()->toDateString(),
                'currency' => 'AFN',
                'subtotal' => '1000.0000',
                'discount_total' => '100.0000',
                'landed_cost_total' => '50.0000',
                'grand_total' => '950.0000',
                'created_by' => $user->id,
            ]);
            $orderLine = $order->lines()->create([
                'medicine_id' => $medicine->id,
                'description' => 'Cost Medicine',
                'ordered_quantity' => '10.0000',
                'received_quantity' => '0.0000',
                'unit_cost' => '100.0000',
                'discount_amount' => '100.0000',
                'landed_cost_allocated' => '50.0000',
                'line_total' => '950.0000',
            ]);
            $receipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $order->id,
                'supplier_id' => $supplier->id,
                'receipt_number' => 'GRN-COST-001',
                'status' => 'pending_inventory',
                'received_at' => now(),
                'created_by' => $user->id,
            ]);
            $receipt->lines()->create([
                'purchase_order_line_id' => $orderLine->id,
                'medicine_id' => $medicine->id,
                'received_quantity' => '5.0000',
                'bonus_quantity' => '1.0000',
                'batch_number' => 'LOT-001',
                'manufactured_at' => now()->subMonth()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
                'unit_cost' => '100.0000',
                'sale_price' => '125.0000',
            ]);

            $poster = app(PostGoodsReceiptToInventory::class);
            $poster->post($receipt, $location, $user->id);
            $poster->post($receipt, $location, $user->id);

            $batch = ProductBatch::query()->firstOrFail();
            $this->assertSame('6.0000', $batch->received_quantity);
            $this->assertSame('6.0000', $batch->available_quantity);
            $this->assertSame('79.1667', $batch->purchase_cost);
            $this->assertSame('125.0000', $batch->sale_price);
            $this->assertDatabaseCount('stock_movements', 1);
            $this->assertSame('79.1667', StockMovement::query()->firstOrFail()->unit_cost);
            $this->assertSame('5.0000', $orderLine->fresh()->received_quantity);
            $this->assertSame('partially_received', $order->fresh()->status);
            $this->assertNotNull($receipt->fresh()->inventory_posted_at);
        });
    }

    public function test_stock_movement_rejects_negative_balance(): void
    {
        $tenant = $this->createTenant(['name' => 'Guard Pharmacy', 'slug' => 'guard-pharmacy']);
        $location = app(InventoryProvisioner::class)->ensureDefaults($tenant);

        app(TenantContext::class)->run($tenant, function () use ($location): void {
            $medicine = Medicine::query()->create([
                'medicine_code' => 'MED-GUARD',
                'brand_name' => 'Guard Medicine',
                'purchase_unit' => 'box',
                'sale_unit' => 'unit',
                'units_per_purchase_unit' => 1,
                'reorder_level' => 0,
            ]);
            $batch = ProductBatch::query()->create([
                'medicine_id' => $medicine->id,
                'branch_id' => $location->branch_id,
                'stock_location_id' => $location->id,
                'batch_number' => 'GUARD-1',
                'batch_key' => hash('sha256', 'GUARD-1'),
                'status' => 'active',
                'received_quantity' => '2.0000',
                'available_quantity' => '2.0000',
                'purchase_cost' => '10.0000',
            ]);

            try {
                app(StockMovementService::class)->record(
                    $batch,
                    '-3.0000',
                    'adjustment',
                    'test',
                    'negative',
                    'negative-test',
                    null,
                );
                $this->fail('Expected negative stock validation to fail.');
            } catch (ValidationException) {
                $this->assertSame('2.0000', $batch->fresh()->available_quantity);
                $this->assertDatabaseCount('stock_movements', 0);
            }
        });
    }

    public function test_fefo_skips_expired_and_quarantined_batches(): void
    {
        $tenant = $this->createTenant(['name' => 'FEFO Pharmacy', 'slug' => 'fefo-pharmacy']);
        $location = app(InventoryProvisioner::class)->ensureDefaults($tenant);

        app(TenantContext::class)->run($tenant, function () use ($location): void {
            $medicine = Medicine::query()->create([
                'medicine_code' => 'MED-FEFO',
                'brand_name' => 'FEFO Medicine',
                'purchase_unit' => 'box',
                'sale_unit' => 'tablet',
                'units_per_purchase_unit' => 10,
                'reorder_level' => 5,
            ]);

            $expired = $this->makeBatch($medicine, $location, 'EXPIRED', now()->subDay()->toDateString(), 'active');
            $quarantined = $this->makeBatch($medicine, $location, 'QUAR', now()->addDays(10)->toDateString(), 'quarantined');
            $early = $this->makeBatch($medicine, $location, 'EARLY', now()->addDays(20)->toDateString(), 'active');
            $late = $this->makeBatch($medicine, $location, 'LATE', now()->addDays(60)->toDateString(), 'active');

            $allocations = app(FefoAllocator::class)->consume(
                $medicine,
                $location,
                '6.0000',
                'test_sale',
                'SALE-001',
                'sale-001',
                null,
            );

            $this->assertCount(2, $allocations);
            $this->assertSame($early->id, $allocations[0]['product_batch_id']);
            $this->assertSame('5.0000', $allocations[0]['quantity']);
            $this->assertSame($late->id, $allocations[1]['product_batch_id']);
            $this->assertSame('1.0000', $allocations[1]['quantity']);
            $this->assertSame('5.0000', $expired->fresh()->available_quantity);
            $this->assertSame('5.0000', $quarantined->fresh()->available_quantity);
            $this->assertSame('0.0000', $early->fresh()->available_quantity);
            $this->assertSame('4.0000', $late->fresh()->available_quantity);
        });
    }

    private function makeBatch(
        Medicine $medicine,
        $location,
        string $batchNumber,
        string $expiresAt,
        string $status,
    ): ProductBatch {
        return ProductBatch::query()->create([
            'medicine_id' => $medicine->id,
            'branch_id' => $location->branch_id,
            'stock_location_id' => $location->id,
            'batch_number' => $batchNumber,
            'batch_key' => hash('sha256', $batchNumber),
            'expires_at' => $expiresAt,
            'status' => $status,
            'received_quantity' => '5.0000',
            'available_quantity' => '5.0000',
            'purchase_cost' => '10.0000',
        ]);
    }
}
