<?php

namespace Tests\Feature\Pharmacy;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseTotalsCalculator;
use App\Services\Purchasing\RecordSupplierPayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
}
