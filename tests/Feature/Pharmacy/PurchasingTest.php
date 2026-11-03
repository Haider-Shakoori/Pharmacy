<?php

namespace Tests\Feature\Pharmacy;

use App\Models\GoodsReceipt;
use App\Models\Medicine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Mobile\MobileGoodsReceiptService;
use App\Services\Purchasing\PurchaseTotalsCalculator;
use App\Services\Purchasing\RecordSupplierPayment;
use App\Services\Subscriptions\TrialProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_records_are_isolated_by_pharmacy_database(): void
    {
        $tenantA = $this->createTenant(['name' => 'A Pharmacy', 'slug' => 'purchase-a']);
        $tenantB = $this->createTenant(['name' => 'B Pharmacy', 'slug' => 'purchase-b']);
        app(TenantContext::class)->run($tenantA, fn () => Supplier::query()->create([
            'code' => 'SUP-001',
            'name' => 'Kabul Supplier',
        ]));

        app(TenantContext::class)->run($tenantB, function (): void {
            $this->assertSame(0, Supplier::query()->count());
        });

        $this->assertFalse(Schema::connection('central')->hasTable('suppliers'));
    }

    public function test_purchase_totals_use_exact_decimal_arithmetic(): void
    {
        $totals = app(PurchaseTotalsCalculator::class)->calculate([
            [
                'ordered_quantity' => '3.25',
                'unit_cost' => '12.3456',
                'discount_amount' => '0.1234',
                'landed_cost_allocated' => '1.0000',
            ],
        ]);

        $this->assertSame('40.1232', $totals['subtotal']);
        $this->assertSame('0.1234', $totals['discount_total']);
        $this->assertSame('1.0000', $totals['landed_cost_total']);
        $this->assertSame('40.9998', $totals['grand_total']);
    }

    public function test_supplier_payment_is_idempotent_and_updates_invoice_balance_once(): void
    {
        $tenant = $this->createTenant(['name' => 'Payment Pharmacy', 'slug' => 'payment-pharmacy']);

        app(TenantContext::class)->run($tenant, function (): void {
            $user = User::query()->create([
                'name' => 'Accountant',
                'email' => 'accountant@example.test',
                'password' => 'password123',
            ]);

            $supplier = Supplier::query()->create(['code' => 'SUP-001', 'name' => 'Supplier']);
            $invoice = PurchaseInvoice::query()->create([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PINV-001',
                'invoice_date' => now()->toDateString(),
                'currency' => 'AFN',
                'status' => 'open',
                'subtotal' => '100.0000',
                'grand_total' => '100.0000',
                'paid_total' => '0.0000',
                'balance_due' => '100.0000',
                'created_by' => $user->id,
            ]);

            $data = [
                'amount' => '10.0000',
                'currency' => 'AFN',
                'method' => 'hawala',
                'reference' => 'H-001',
                'paid_at' => now(),
                'idempotency_key' => 'same-payment-key',
                'notes' => null,
            ];

            $first = app(RecordSupplierPayment::class)->record($invoice, $data, $user->id);
            $second = app(RecordSupplierPayment::class)->record($invoice, $data, $user->id);

            $this->assertTrue($first->is($second));
            $this->assertDatabaseCount('supplier_payments', 1);

            $invoice->refresh();
            $this->assertSame('10.0000', $invoice->paid_total);
            $this->assertSame('90.0000', $invoice->balance_due);
            $this->assertSame('partially_paid', $invoice->status);
        });
    }

    public function test_owner_can_approve_po_and_retry_goods_receipt_without_duplicate_stock_source(): void
    {
        $tenant = $this->createTenant(['name' => 'Flow Pharmacy', 'slug' => 'flow-pharmacy']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner(
            $tenant,
            'Owner',
            'owner@flow.test',
            'password123',
        );

        [$supplier, $medicine] = app(TenantContext::class)->run($tenant, function (): array {
            $supplier = Supplier::query()->create(['code' => 'SUP-FLOW', 'name' => 'Flow Supplier']);
            $medicine = Medicine::query()->create([
                'medicine_code' => 'MED-FLOW',
                'brand_name' => 'Flow Medicine',
                'purchase_unit' => 'box',
                'sale_unit' => 'tablet',
                'units_per_purchase_unit' => 10,
                'reorder_level' => 5,
            ]);

            return [$supplier, $medicine];
        });

        $this->actingAs($owner);
        $this->onTenantDomain($tenant)->post('/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'currency' => 'AFN',
            'lines' => [[
                'medicine_id' => $medicine->id,
                'ordered_quantity' => '10',
                'unit_cost' => '25.5000',
                'discount_amount' => '5.0000',
                'landed_cost_allocated' => '10.0000',
            ]],
        ])->assertRedirect();

        $order = app(TenantContext::class)->run(
            $tenant,
            fn () => PurchaseOrder::query()->with('lines')->firstOrFail(),
        );

        $this->onTenantDomain($tenant)->post("/purchase-orders/{$order->id}/submit")->assertRedirect();
        $this->onTenantDomain($tenant)->post("/purchase-orders/{$order->id}/approve")->assertRedirect();

        $order = app(TenantContext::class)->run(
            $tenant,
            fn () => PurchaseOrder::query()->with('lines')->findOrFail($order->id),
        );

        $line = $order->lines->firstOrFail();
        $payload = [
            'received_at' => now()->format('Y-m-d H:i:s'),
            'idempotency_key' => 'grn-flow-key',
            'lines' => [[
                'purchase_order_line_id' => $line->id,
                'received_quantity' => '5',
                'bonus_quantity' => '1',
                'batch_number' => 'BATCH-FLOW-01',
                'manufactured_at' => now()->subMonth()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
                'unit_cost' => '25.5000',
                'sale_price' => '35.0000',
            ]],
        ];

        $this->onTenantDomain($tenant)
            ->post("/purchase-orders/{$order->id}/receipts", $payload)
            ->assertRedirect();

        $this->onTenantDomain($tenant)
            ->post("/purchase-orders/{$order->id}/receipts", $payload)
            ->assertRedirect();

        app(TenantContext::class)->run($tenant, function () use ($line): void {
            $this->assertSame(1, GoodsReceipt::query()->count());

            $receipt = GoodsReceipt::query()->with('lines')->firstOrFail();
            $this->assertSame('pending_inventory', $receipt->status);
            $this->assertNull($receipt->inventory_posted_at);
            $this->assertSame('1.0000', $receipt->lines->firstOrFail()->bonus_quantity);
            $this->assertSame('0.0000', $line->fresh()->received_quantity);
        });
    }

    public function test_mobile_goods_receipt_capture_is_idempotent_and_does_not_post_stock(): void
    {
        $tenant = $this->createTenant(['name' => 'Receiving Pharmacy', 'slug' => 'mobile-receiving']);
        app(TenantContext::class)->run($tenant, function (): void {
            $user = User::query()->create([
                'name' => 'Receiver',
                'email' => 'receiver@example.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $supplier = Supplier::query()->create(['code' => 'SUP-MOBILE', 'name' => 'Mobile Supplier']);
            $medicine = Medicine::query()->create([
                'medicine_code' => 'MED-MOBILE',
                'brand_name' => 'Mobile Medicine',
                'purchase_unit' => 'box',
                'sale_unit' => 'tablet',
                'units_per_purchase_unit' => 1,
            ]);
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $supplier->id,
                'number' => 'PO-MOBILE-001',
                'status' => 'approved',
                'order_date' => today()->toDateString(),
                'currency' => 'AFN',
                'created_by' => $user->id,
            ]);
            $line = $order->lines()->create([
                'medicine_id' => $medicine->id,
                'description' => 'Mobile Medicine',
                'ordered_quantity' => '10.0000',
                'received_quantity' => '0.0000',
                'unit_cost' => '5.0000',
                'line_total' => '50.0000',
            ]);
            $payload = [
                'purchase_order_id' => $order->id,
                'received_at' => now()->toDateTimeString(),
                'lines' => [[
                    'purchase_order_line_id' => $line->id,
                    'received_quantity' => '4.0000',
                    'bonus_quantity' => '1.0000',
                    'batch_number' => 'MOBILE-BATCH-001',
                    'expires_at' => now()->addYear()->toDateString(),
                    'unit_cost' => '5.0000',
                ]],
            ];

            $service = app(MobileGoodsReceiptService::class);
            $first = $service->capture($user, $payload, 'mobile-receipt-001');
            $second = $service->capture($user, $payload, 'mobile-receipt-001');
            $this->assertSame($first->id, $second->id);
            $this->assertDatabaseCount('goods_receipts', 1);
            $this->assertSame('pending_inventory', $first->status);
            $this->assertNull($first->inventory_posted_at);
            $this->assertSame('0.0000', $line->fresh()->received_quantity);

            try {
                $service->capture($user, [
                    ...$payload,
                    'lines' => [[
                        ...$payload['lines'][0],
                        'received_quantity' => '7.0000',
                    ]],
                ], 'mobile-receipt-002');
                $this->fail('Pending receipts must count against outstanding quantity.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('lines', $exception->errors());
            }
            $this->assertDatabaseCount('goods_receipts', 1);

            // Different receipt keys must still respect quantities reserved by
            // an unposted receipt, even when the order itself is unchanged.
            $this->assertSame('0.0000', $line->fresh()->received_quantity);
            $this->assertSame('pending_inventory', $first->fresh()->status);
            $this->assertSame('4.0000', $first->lines()->firstOrFail()->received_quantity);
            $this->assertSame('1.0000', $first->lines()->firstOrFail()->bonus_quantity);
        });
    }

    public function test_mobile_receipt_rejects_missing_tracking_and_duplicate_lines_without_stock_changes(): void
    {
        $tenant = $this->createTenant(['name' => 'Tracked Pharmacy', 'slug' => 'tracked-receiving']);
        app(TenantContext::class)->run($tenant, function (): void {
            $user = User::query()->create([
                'name' => 'Receiver',
                'email' => 'tracked-receiver@example.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $supplier = Supplier::query()->create(['code' => 'SUP-TRACK', 'name' => 'Tracked Supplier']);
            $medicine = Medicine::query()->create([
                'medicine_code' => 'MED-TRACK',
                'brand_name' => 'Tracked Medicine',
                'purchase_unit' => 'box',
                'sale_unit' => 'tablet',
                'units_per_purchase_unit' => 1,
                'batch_tracking_required' => true,
                'expiry_tracking_required' => true,
            ]);
            $order = PurchaseOrder::query()->create([
                'supplier_id' => $supplier->id,
                'number' => 'PO-TRACK-001',
                'status' => 'approved',
                'order_date' => today()->toDateString(),
                'currency' => 'AFN',
                'created_by' => $user->id,
            ]);
            $line = $order->lines()->create([
                'medicine_id' => $medicine->id,
                'description' => 'Tracked Medicine',
                'ordered_quantity' => '10.0000',
                'received_quantity' => '0.0000',
                'unit_cost' => '5.0000',
                'line_total' => '50.0000',
            ]);
            $data = [
                'purchase_order_id' => $order->id,
                'received_at' => now()->toDateTimeString(),
                'lines' => [[
                    'purchase_order_line_id' => $line->id,
                    'received_quantity' => '2.0000',
                    'unit_cost' => '5.0000',
                ]],
            ];
            $service = app(MobileGoodsReceiptService::class);

            try {
                $service->capture($user, $data, 'missing-tracking');
                $this->fail('Missing batch tracking must reject receipt.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('lines', $exception->errors());
            }

            $tracked = $data;
            $tracked['lines'][0]['batch_number'] = 'TRACK-001';
            try {
                $service->capture($user, $tracked, 'missing-expiry');
                $this->fail('Missing expiry tracking must reject receipt.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('lines', $exception->errors());
            }

            $tracked['lines'][0]['expires_at'] = now()->addYear()->toDateString();
            $tracked['lines'][] = $tracked['lines'][0];
            try {
                $service->capture($user, $tracked, 'duplicate-lines');
                $this->fail('Duplicate purchase-order lines must reject receipt.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('lines', $exception->errors());
            }

            $this->assertDatabaseCount('goods_receipts', 0);
            $this->assertSame('0.0000', $line->fresh()->received_quantity);
        });
    }
}
