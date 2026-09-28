<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

class AccountingProvisioner
{
    /** @var array<string, bool> */
    private array $ensured = [];

    /** @var array<string, array<string, LedgerAccount>> */
    private array $accountCache = [];

    public const ACCOUNTS = [
        'cash_on_hand' => ['1000', 'Cash on Hand', 'asset', 'debit'],
        'bank' => ['1010', 'Bank', 'asset', 'debit'],
        'mobile_money' => ['1020', 'Mobile Money', 'asset', 'debit'],
        'hawala_clearing' => ['1030', 'Hawala Clearing', 'asset', 'debit'],
        'other_clearing' => ['1040', 'Other Settlement Clearing', 'asset', 'debit'],
        'accounts_receivable' => ['1100', 'Accounts Receivable', 'asset', 'debit'],
        'inventory' => ['1200', 'Inventory', 'asset', 'debit'],
        'accounts_payable' => ['2000', 'Accounts Payable', 'liability', 'credit'],
        'sales_tax_payable' => ['2100', 'Sales Tax Payable', 'liability', 'credit'],
        'owner_equity' => ['3000', 'Owner Equity', 'equity', 'credit'],
        'owner_drawings' => ['3100', 'Owner Drawings', 'equity', 'debit'],
        'sales_revenue' => ['4000', 'Sales Revenue', 'revenue', 'credit'],
        'sales_discounts' => ['4010', 'Sales Discounts', 'revenue', 'debit'],
        'sales_returns' => ['4020', 'Sales Returns', 'revenue', 'debit'],
        'cost_of_goods_sold' => ['5000', 'Cost of Goods Sold', 'expense', 'debit'],
        'inventory_adjustment' => ['5100', 'Inventory Adjustments', 'expense', 'debit'],
        'operating_expense' => ['6000', 'Operating Expenses', 'expense', 'debit'],
        'cash_over_short' => ['6100', 'Cash Over / Short', 'expense', 'debit'],
    ];

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function ensureDefaults(?Tenant $tenant = null): void
    {
        if ($tenant !== null && (! tenancy()->initialized || (string) tenant('id') !== (string) $tenant->id)) {
            $this->tenantContext->run($tenant, fn () => $this->ensureDefaults());

            return;
        }

        $context = $this->contextKey();
        if ($this->ensured[$context] ?? false) {
            return;
        }

        foreach (self::ACCOUNTS as $systemKey => [$code, $name, $type, $normalBalance]) {
            $account = LedgerAccount::query()->updateOrCreate(
                ['system_key' => $systemKey],
                [
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $normalBalance,
                    'currency' => 'AFN',
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
            $this->accountCache[$context][$systemKey] = $account;
        }

        $this->ensured[$context] = true;
    }

    public function account(string $systemKey): LedgerAccount
    {
        $context = $this->contextKey();
        if (isset($this->accountCache[$context][$systemKey])) {
            return $this->accountCache[$context][$systemKey];
        }

        $this->ensureDefaults();

        return $this->accountCache[$context][$systemKey] ??= LedgerAccount::query()
            ->where('system_key', $systemKey)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function contextKey(): string
    {
        return tenancy()->initialized ? (string) tenant('id') : 'no-tenant';
    }
}
