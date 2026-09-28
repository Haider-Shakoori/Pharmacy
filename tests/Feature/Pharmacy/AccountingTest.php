<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Accounting\AccountingProvisioner;
use App\Services\Accounting\ExpenseService;
use App\Services\Accounting\OperationalAccountingService;
use App\Services\Inventory\StockMovementService;
use App\Services\Purchasing\RecordSupplierPayment;
use App\Services\Subscriptions\TrialProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_tables_and_chart_are_tenant_only(): void
    {
        $tenantA = $this->createTenant(['slug' => 'accounting-a']);
        $tenantB = $this->createTenant(['slug' => 'accounting-b']);

        app(AccountingProvisioner::class)->ensureDefaults($tenantA);

        app(TenantContext::class)->run($tenantA, function (): void {
            $this->assertGreaterThanOrEqual(18, LedgerAccount::query()->count());
            $this->assertTrue(LedgerAccount::query()->where('system_key', 'accounts_receivable')->exists());
            $this->assertTrue(LedgerAccount::query()->where('system_key', 'cost_of_goods_sold')->exists());
        });

        app(TenantContext::class)->run($tenantB, function (): void {
            $this->assertSame(0, LedgerAccount::query()->count());
        });

        $this->assertFalse(Schema::connection('central')->hasTable('ledger_accounts'));
        $this->assertFalse(Schema::connection('central')->hasTable('journal_entries'));
    }

    public function test_completed_sale_posts_balanced_source_linked_journal_exactly_once(): void
    {
        $tenant = $this->createTenant(['slug' => 'accounting-pos']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@accounting-pos.test', 'password123');

        [$location, $medicine] = app(TenantContext::class)->run($tenant, function () use ($owner): array {
            $medicine = Medicine::query()->create([
                'medicine_code' => 'ACC-MED',
                'brand_name' => 'Accounting Medicine',
                'sale_unit' => 'tablet',
            ]);
            $branch = Branch::query()->create(['code' => 'ACC', 'name' => 'Accounting Branch', 'is_default' => true, 'is_active' => true]);
            $location = StockLocation::query()->create(['branch_id' => $branch->id, 'code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true, 'is_active' => true]);
            $batch = ProductBatch::query()->create([
                'medicine_id' => $medicine->id,
                'branch_id' => $branch->id,
                'stock_location_id' => $location->id,
                'batch_number' => 'ACC-B1',
                'batch_key' => 'ACC-B1',
                'expires_at' => today()->addYear(),
                'status' => 'active',
                'received_quantity' => 10,
                'available_quantity' => 0,
                'purchase_cost' => '3.0000',
                'sale_price' => '5.0000',
            ]);
            app(StockMovementService::class)->record($batch, '10', 'receipt', 'test', 'acc', 'seed:accounting', $owner->id, null, null, '3.0000');

            return [$location, $medicine];
        });

        $payload = [
            'stock_location_id' => $location->id,
            'idempotency_key' => 'accounting-sale-001',
            'lines' => [[
                'medicine_id' => $medicine->id,
                'quantity' => '4',
                'discount_amount' => '2.0000',
            ]],
            'payments' => [['method' => 'cash', 'amount' => '18.0000']],
        ];

        $host = $this->tenantHost($tenant);
        $this->actingAs($owner);
        $this->onTenantDomain($tenant)->withHeader('Host', $host)->postJson('/pos/sales', $payload)->assertCreated();
        $this->onTenantDomain($tenant)->withHeader('Host', $host)->postJson('/pos/sales', $payload)->assertCreated();

        app(TenantContext::class)->run($tenant, function (): void {
            $sale = Sale::query()->where('idempotency_key', 'accounting-sale-001')->firstOrFail();
            $entries = JournalEntry::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->get();
            $this->assertCount(1, $entries);

            $entry = $entries->firstOrFail()->load('lines.account');
            $this->assertSame($entry->total_debit, $entry->total_credit);
            $this->assertSame('32.0000', $entry->total_debit);
            $this->assertDatabaseHas('journal_lines', [
                'journal_entry_id' => $entry->id,
                'ledger_account_id' => LedgerAccount::query()->where('system_key', 'cash_on_hand')->value('id'),
                'debit' => 18,
            ]);
            $this->assertDatabaseHas('journal_lines', [
                'journal_entry_id' => $entry->id,
                'ledger_account_id' => LedgerAccount::query()->where('system_key', 'sales_revenue')->value('id'),
                'credit' => 20,
            ]);
            $this->assertDatabaseHas('journal_lines', [
                'journal_entry_id' => $entry->id,
                'ledger_account_id' => LedgerAccount::query()->where('system_key', 'sales_discounts')->value('id'),
                'debit' => 2,
            ]);
            $this->assertDatabaseHas('journal_lines', [
                'journal_entry_id' => $entry->id,
                'ledger_account_id' => LedgerAccount::query()->where('system_key', 'cost_of_goods_sold')->value('id'),
                'debit' => 12,
            ]);
        });
    }

    public function test_purchase_invoice_and_supplier_payment_post_inventory_payable_and_settlement_journals(): void
    {
        $tenant = $this->createTenant(['slug' => 'accounting-purchase']);

        app(TenantContext::class)->run($tenant, function (): void {
            $user = User::query()->create(['name' => 'Accountant', 'email' => 'ap@example.test', 'password' => 'password123']);
            $supplier = Supplier::query()->create(['code' => 'SUP-AP', 'name' => 'AP Supplier']);
            $invoice = PurchaseInvoice::query()->create([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PINV-AP-001',
                'invoice_date' => now()->toDateString(),
                'currency' => 'AFN',
                'status' => 'open',
                'subtotal' => '100.0000',
                'grand_total' => '100.0000',
                'paid_total' => '0.0000',
                'balance_due' => '100.0000',
                'created_by' => $user->id,
            ]);

            app(OperationalAccountingService::class)->postPurchaseInvoice($invoice);
            app(OperationalAccountingService::class)->postPurchaseInvoice($invoice);

            $payment = app(RecordSupplierPayment::class)->record($invoice, [
                'amount' => '25.0000',
                'currency' => 'AFN',
                'method' => 'hawala',
                'reference' => 'HW-25',
                'paid_at' => now(),
                'idempotency_key' => 'ap-pay-001',
                'notes' => null,
            ], $user->id);

            $this->assertSame(2, JournalEntry::query()->count());
            $this->assertSame(1, JournalEntry::query()->where('source_type', PurchaseInvoice::class)->count());
            $this->assertSame(1, JournalEntry::query()->where('source_id', $payment->id)->count());

            $invoiceEntry = JournalEntry::query()->where('source_type', PurchaseInvoice::class)->firstOrFail();
            $this->assertSame('100.0000', $invoiceEntry->total_debit);
            $this->assertSame('100.0000', $invoiceEntry->total_credit);
            $this->assertDatabaseHas('journal_lines', [
                'journal_entry_id' => $invoiceEntry->id,
                'ledger_account_id' => LedgerAccount::query()->where('system_key', 'inventory')->value('id'),
                'debit' => 100,
            ]);
            $this->assertDatabaseHas('journal_lines', [
                'journal_entry_id' => $invoiceEntry->id,
                'ledger_account_id' => LedgerAccount::query()->where('system_key', 'accounts_payable')->value('id'),
                'credit' => 100,
            ]);

            $paymentEntry = JournalEntry::query()->where('source_id', $payment->id)->firstOrFail();
            $this->assertDatabaseHas('journal_lines', [
                'journal_entry_id' => $paymentEntry->id,
                'ledger_account_id' => LedgerAccount::query()->where('system_key', 'hawala_clearing')->value('id'),
                'credit' => 25,
            ]);
        });
    }

    public function test_expense_reversal_preserves_original_and_posts_opposite_journal(): void
    {
        $tenant = $this->createTenant(['slug' => 'accounting-expense']);

        app(TenantContext::class)->run($tenant, function (): void {
            $user = User::query()->create(['name' => 'Accountant', 'email' => 'expense@example.test', 'password' => 'password123']);
            $accounts = app(AccountingProvisioner::class);
            $accounts->ensureDefaults();

            $expense = app(ExpenseService::class)->post([
                'expense_account_id' => $accounts->account('operating_expense')->id,
                'payment_account_id' => $accounts->account('cash_on_hand')->id,
                'business_date' => now()->toDateString(),
                'currency' => 'AFN',
                'amount' => '40.0000',
                'payee' => 'Landlord',
                'reference' => 'RENT-001',
                'notes' => 'Rent',
                'idempotency_key' => 'expense-001',
            ], $user->id);

            app(ExpenseService::class)->reverse($expense, 'Entered against wrong period', $user->id);

            $this->assertSame('reversed', $expense->fresh()->status);
            $this->assertSame(2, JournalEntry::query()->where('source_type', Expense::class)->where('source_id', $expense->id)->count());

            $original = JournalEntry::query()->where('source_type', Expense::class)->where('source_id', $expense->id)->whereNull('reversal_of_id')->firstOrFail();
            $reversal = JournalEntry::query()->where('reversal_of_id', $original->id)->firstOrFail();
            $this->assertSame('reversed', $original->status);
            $this->assertSame('40.0000', $original->total_debit);
            $this->assertSame('40.0000', $reversal->total_debit);
        });
    }

    public function test_accounting_workspace_is_visible_to_owner_and_forbidden_to_cashier(): void
    {
        $tenant = $this->createTenant(['slug' => 'accounting-ui']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@accounting-ui.test', 'password123');

        $cashier = app(TenantContext::class)->run($tenant, function () use ($tenant): User {
            $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);
            $cashier = User::query()->create([
                'name' => 'Cashier',
                'email' => 'cashier@accounting-ui.test',
                'password' => 'password123',
                'is_active' => true,
            ]);
            $cashier->roles()->sync([$roles['cashier']->id]);

            return $cashier;
        });

        $host = $this->tenantHost($tenant);
        $this->onTenantDomain($tenant);

        $this->actingAs($owner)->withHeader('Host', $host)->get('/accounting')
            ->assertOk()
            ->assertSee('Accounting')
            ->assertSee('Trial balance');

        $this->actingAs($cashier)->withHeader('Host', $host)->get('/accounting')
            ->assertForbidden();
    }
}
