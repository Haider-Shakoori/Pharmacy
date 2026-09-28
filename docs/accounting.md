# Tenant Accounting Foundation

Batch 15 adds a source-linked double-entry ledger inside each pharmacy database. The central SaaS database does not contain pharmacy chart-of-account, journal, expense, receivable, payable, or profit records.

## Core rules

- Every journal entry must balance exactly: total debits equal total credits.
- Monetary values use fixed-precision `DECIMAL(20,4)` storage and `brick/math` decimal arithmetic in posting services. No floating-point arithmetic is used to post journals.
- Every automatic journal has `source_type`, `source_id`, source event/number, and a unique idempotency key.
- Original journals are never edited to correct money. Authorized corrections create reversal journals.
- Operational writes and their accounting journals execute inside the same tenant database transaction.
- Retried POS sales, supplier payments, returns, and accounting source actions cannot double-post because the source transaction and journal both have idempotency controls.
- Platform subscription/payment money remains central SaaS accounting and is not part of this tenant ledger.

## Default chart of accounts

The baseline chart is created idempotently for every new tenant and lazily verified for existing tenants:

| Code | System key | Type | Normal balance |
| --- | --- | --- | --- |
| 1000 | cash_on_hand | Asset | Debit |
| 1010 | bank | Asset | Debit |
| 1020 | mobile_money | Asset | Debit |
| 1030 | hawala_clearing | Asset | Debit |
| 1040 | other_clearing | Asset | Debit |
| 1100 | accounts_receivable | Asset | Debit |
| 1200 | inventory | Asset | Debit |
| 2000 | accounts_payable | Liability | Credit |
| 2100 | sales_tax_payable | Liability | Credit |
| 3000 | owner_equity | Equity | Credit |
| 3100 | owner_drawings | Equity | Debit |
| 4000 | sales_revenue | Revenue | Credit |
| 4010 | sales_discounts | Contra revenue | Debit |
| 4020 | sales_returns | Contra revenue | Debit |
| 5000 | cost_of_goods_sold | Expense | Debit |
| 5100 | inventory_adjustment | Expense | Debit |
| 6000 | operating_expense | Expense | Debit |
| 6100 | cash_over_short | Expense | Debit |

Tenant accountants may add additional accounts. System accounts are retained as the stable posting targets for operational automation.

## Automatic posting rules

### Completed POS sale

- Debit the actual settlement accounts (cash net of cash change, bank, mobile, and/or customer receivable).
- Debit Sales Discounts for captured line discounts.
- Credit Sales Revenue for gross line revenue.
- Credit Sales Tax Payable when tax is present.
- Debit Cost of Goods Sold using the recorded batch allocation cost.
- Credit Inventory for the same recorded batch allocation cost.

### Sale return

- Debit Sales Returns for the refund value.
- Credit the refund settlement account (cash, bank, mobile, or receivable reduction).
- For quantities actually returned to sellable inventory, debit Inventory and credit Cost of Goods Sold using the original sale allocation cost.
- Expired/damaged/non-restocked returns do not recreate inventory value.

### Supplier invoice

- Debit Inventory for the recorded invoice grand total (including allocated landed cost and discounts already reflected in the purchase total).
- Credit Accounts Payable.

Physical batch valuation is based on the acquisition cost calculated by goods receipt posting: received quantity cost less allocated line discount plus allocated landed cost, spread across received + bonus quantity. This provides the cost used by later FEFO sale allocations and COGS.

Invoice recognition and physical receipt can occur on different dates, so the Accounting page shows ledger inventory and batch stock valuation side by side with their timing difference.

### Supplier payment

- Debit Accounts Payable.
- Credit Cash, Bank, Hawala Clearing, or Other Settlement Clearing according to the recorded payment method.

### Inventory adjustment

- Positive quantity: debit Inventory, credit Inventory Adjustments.
- Negative quantity: debit Inventory Adjustments, credit Inventory.
- Value is calculated from the batch's recorded purchase cost at the time of the adjustment.

### Daily Closing variance

- Cash over: debit Cash on Hand, credit Cash Over / Short.
- Cash shortage: debit Cash Over / Short, credit Cash on Hand.
- Reopening the Daily Closing creates a reversal journal for the posted variance.

### Expense

- Debit the chosen expense account.
- Credit the selected settlement asset account.
- Reversal preserves the original expense and journal and adds an opposite journal.

### Manual accounting adjustment

Authorized accountants can post a documented debit/credit pair with date, reference, reason, and optional location. Reversal is the only correction path in the UI.

## Reports

The Accounting workspace provides:

- Current cash, bank, mobile/hawala, receivable, payable, and inventory ledger balances.
- Period-filtered trial balance.
- Period profit and loss from ledger revenue and expense accounts.
- Receivable and payable aging buckets.
- Batch stock valuation versus ledger inventory reconciliation.
- Source-linked journal history with line-level debits and credits.

## Historical rollout/backfill

After tenant migrations are applied, run:

```bash
php artisan pharmacy:accounting:backfill
```

or target one tenant:

```bash
php artisan pharmacy:accounting:backfill <tenant-ulid>
```

The command is idempotent and derives missing journals from completed sales, completed returns, active supplier invoices, supplier payments, posted inventory adjustments, and currently finalized/approved Daily Closing variances. It does not delete source records or overwrite existing journals.

## Rollback

The database migration can be rolled back only before production financial records depend on it. After live journals exist, use forward migrations and journal reversals rather than dropping accounting tables. Application rollback should keep the accounting schema/data in place until a forward-compatible release is restored.
