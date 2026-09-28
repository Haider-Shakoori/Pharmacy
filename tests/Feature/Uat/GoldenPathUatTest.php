<?php

namespace Tests\Feature\Uat;

use App\Models\DailyClosing;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Inventory\InventoryProvisioner;
use App\Services\Inventory\StockMovementService;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('uat')]
class GoldenPathUatTest extends TestCase
{
    use RefreshDatabase;

    public function test_saas_to_sale_to_daily_closing_golden_path(): void
    {
        $tenant = $this->createTenant([
            'name' => 'UAT Pharmacy',
            'slug' => 'uat-pharmacy',
        ]);

        $licenseKey = app(TrialProvisioner::class)->provision($tenant);

        $this->assertIsString($licenseKey);
        $this->assertNotSame('', $licenseKey);

        app(RbacProvisioner::class)->provisionOwner(
            $tenant,
            'UAT Owner',
            'owner@uat.test',
            'password123',
        );
        app(InventoryProvisioner::class)->ensureDefaults($tenant);

        $tenant->run(function (): void {
            $owner = User::query()
                ->where('email', 'owner@uat.test')
                ->firstOrFail();
            $location = StockLocation::query()
                ->where('code', 'MAIN')
                ->firstOrFail();

            $medicine = Medicine::query()->create([
                'medicine_code' => 'UAT-PARA-500',
                'brand_name' => 'UAT Paracetamol',
                'strength' => '500 mg',
                'purchase_unit' => 'box',
                'sale_unit' => 'tablet',
                'units_per_purchase_unit' => 10,
                'reorder_level' => 5,
            ]);

            $batch = ProductBatch::query()->create([
                'medicine_id' => $medicine->id,
                'branch_id' => $location->branch_id,
                'stock_location_id' => $location->id,
                'batch_number' => 'UAT-LOT-001',
                'batch_key' => hash('sha256', 'UAT-LOT-001'),
                'expires_at' => today()->addMonths(6),
                'status' => 'active',
                'received_quantity' => '20.0000',
                'available_quantity' => '0.0000',
                'purchase_cost' => '4.0000',
                'sale_price' => '10.0000',
            ]);

            app(StockMovementService::class)->record(
                $batch,
                '20.0000',
                'receipt',
                'uat',
                'opening-stock',
                'uat:opening-stock:1',
                $owner->id,
            );
        });

        $host = $this->tenantHost($tenant);
        $this->onTenantDomain($tenant);

        $tenant->run(function () use ($host): void {
            $owner = User::query()
                ->where('email', 'owner@uat.test')
                ->firstOrFail();
            $location = StockLocation::query()
                ->where('code', 'MAIN')
                ->firstOrFail();
            $medicine = Medicine::query()
                ->where('medicine_code', 'UAT-PARA-500')
                ->firstOrFail();

            $salePayload = [
                'stock_location_id' => $location->id,
                'idempotency_key' => 'uat-sale-001',
                'lines' => [[
                    'medicine_id' => $medicine->id,
                    'quantity' => '3.0000',
                ]],
                'payments' => [[
                    'method' => 'cash',
                    'amount' => '30.0000',
                ]],
            ];

            $this->actingAs($owner)
                ->withHeader('Host', $host)
                ->postJson('/pos/sales', $salePayload)
                ->assertCreated();

            $this->assertDatabaseHas('sales', [
                'idempotency_key' => 'uat-sale-001',
                'status' => 'completed',
            ]);
            $this->assertDatabaseHas('product_batches', [
                'batch_number' => 'UAT-LOT-001',
                'available_quantity' => 17,
            ]);
            $this->assertSame(1, Sale::query()->count());

            $this->actingAs($owner)
                ->withHeader('Host', $host)
                ->post('/daily-closing/finalize', [
                    'stock_location_id' => $location->id,
                    'counted_cash' => '30.0000',
                    'notes' => 'Batch 29 UAT close',
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $closing = DailyClosing::query()->firstOrFail();

            $this->assertSame('finalized', $closing->status);
            $this->assertSame('30.0000', $closing->cash_collected);
            $this->assertSame('30.0000', $closing->expected_cash);
            $this->assertSame('0.0000', $closing->variance);

            $salePayload['idempotency_key'] = 'uat-sale-after-close';

            $this->actingAs($owner)
                ->withHeader('Host', $host)
                ->postJson('/pos/sales', $salePayload)
                ->assertUnprocessable();

            $this->assertSame(1, Sale::query()->count());
            $this->assertDatabaseHas('product_batches', [
                'batch_number' => 'UAT-LOT-001',
                'available_quantity' => 17,
            ]);
        });
    }
}
