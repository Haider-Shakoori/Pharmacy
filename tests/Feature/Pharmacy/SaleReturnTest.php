<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleBatchAllocation;
use App\Models\SaleLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\StockMovementService;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_restores_only_original_eligible_batch_and_cannot_be_duplicated(): void
    {
        $tenant = $this->createTenant(['slug' => 'return-test']);
        app(TrialProvisioner::class)->provision($tenant);

        $tenant->run(function () use ($tenant): void {
            $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);
            $user = User::query()->create(['name' => 'Owner', 'email' => 'owner@return.test', 'password' => 'password', 'is_active' => true]);
            $user->roles()->sync([$roles['owner']->id]);
            $medicine = Medicine::query()->create(['medicine_code' => 'MED-RET', 'brand_name' => 'Return Medicine']);
            $branch = Branch::query()->create(['code' => 'RET', 'name' => 'Return Branch', 'is_active' => true]);
            $location = StockLocation::query()->create(['branch_id' => $branch->id, 'code' => 'MAIN', 'name' => 'Main', 'is_active' => true]);
            $batch = ProductBatch::query()->create([
                'medicine_id' => $medicine->id, 'branch_id' => $branch->id, 'stock_location_id' => $location->id,
                'batch_number' => 'B1', 'batch_key' => 'B1', 'expires_at' => today()->addMonth(), 'status' => 'active',
                'received_quantity' => 10, 'available_quantity' => 0, 'purchase_cost' => 4, 'sale_price' => 10,
            ]);
            app(StockMovementService::class)->record($batch, '10', 'receipt', 'test', 'seed', 'ret:seed', $user->id);
            $sale = Sale::query()->create([
                'sale_number' => 'POS-RET', 'stock_location_id' => $location->id, 'business_date' => today(),
                'status' => 'completed', 'currency' => 'AFN', 'subtotal' => 20, 'discount_total' => 0, 'tax_total' => 0,
                'grand_total' => 20, 'paid_total' => 20, 'due_total' => 0, 'change_total' => 0, 'payment_status' => 'paid',
                'created_by' => $user->id, 'completed_at' => now(),
            ]);
            $line = SaleLine::query()->create([
                'sale_id' => $sale->id, 'medicine_id' => $medicine->id, 'description' => 'Return Medicine',
                'sale_unit' => 'unit', 'quantity' => 2, 'unit_price' => 10, 'discount_amount' => 0, 'tax_amount' => 0,
                'line_total' => 20, 'cost_total' => 8, 'prescription_required' => false,
            ]);
            $movement = app(StockMovementService::class)->record($batch, '-2', 'sale', 'sale', $sale->id, 'ret:sale', $user->id, $line->id);
            SaleBatchAllocation::query()->create([
                'sale_line_id' => $line->id, 'product_batch_id' => $batch->id, 'stock_movement_id' => $movement->id,
                'quantity' => 2, 'unit_cost' => 4,
            ]);
        });

        $host = $this->tenantHost($tenant);
        $this->onTenantDomain($tenant);

        $tenant->run(function () use ($host): void {
            $user = User::query()->where('email', 'owner@return.test')->firstOrFail();
            $sale = Sale::query()->where('sale_number', 'POS-RET')->firstOrFail();
            $line = $sale->lines()->firstOrFail();
            $payload = [
                'idempotency_key' => 'return-001',
                'reason' => 'Customer returned one item',
                'lines' => [['sale_line_id' => $line->id, 'quantity' => 1]],
                'refunds' => [['method' => 'cash', 'amount' => 10]],
            ];

            $this->actingAs($user)->withHeader('Host', $host)->post('/pos/sales/'.$sale->id.'/returns', $payload)->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('sale_returns', ['sale_id' => $sale->id, 'refund_total' => 10, 'status' => 'completed']);
            $this->assertDatabaseHas('product_batches', ['batch_number' => 'B1', 'available_quantity' => 9]);
            $this->assertSame(1, StockMovement::query()->where('movement_type', 'sale_return')->count());

            $this->actingAs($user)->withHeader('Host', $host)->post('/pos/sales/'.$sale->id.'/returns', $payload)->assertRedirect();
            $this->assertSame(1, StockMovement::query()->where('movement_type', 'sale_return')->count());
        });
    }
}
