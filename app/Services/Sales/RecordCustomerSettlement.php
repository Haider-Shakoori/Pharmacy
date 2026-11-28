<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\CustomerSettlement;
use App\Models\Sale;
use App\Services\Accounting\AccountingProvisioner;
use App\Services\Accounting\LedgerPostingService;
use App\Services\DailyClosing\DailyClosingService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCustomerSettlement
{
    public function __construct(private AccountingProvisioner $accounts, private LedgerPostingService $ledger, private DailyClosingService $closing) {}

    public function record(Customer $customer, string $saleId, array $data, int $userId): CustomerSettlement
    {
        return DB::transaction(function () use ($customer, $saleId, $data, $userId) {
            $sale = Sale::query()->where('customer_id', $customer->id)->where('status', 'completed')->lockForUpdate()->findOrFail($saleId);
            $existing = CustomerSettlement::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->sale_id === $sale->id && $existing->created_by === $userId, 409);

                return $existing;
            }
            $date = $this->closing->businessDate();
            if ($this->closing->salesBlocked($sale->location, $date)) {
                throw ValidationException::withMessages(['amount' => 'Daily closing has already been finalized.']);
            }
            $amount = BigDecimal::of((string) $data['amount'])->toScale(4);
            $due = BigDecimal::of($sale->due_total);
            if (! $amount->isPositive() || $amount->isGreaterThan($due)) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds outstanding balance or is invalid.']);
            }
            $payment = CustomerSettlement::create([...$data, 'sale_id' => $sale->id, 'currency' => $sale->currency, 'business_date' => $date, 'paid_at' => now(), 'created_by' => $userId]);
            $remaining = $due->minus($amount);
            $sale->update(['due_total' => (string) $remaining, 'paid_total' => (string) BigDecimal::of($sale->paid_total)->plus($amount), 'payment_status' => $remaining->isZero() ? 'paid' : 'partial']);
            $key = match ($payment->method) {
                'cash' => 'cash_on_hand','bank' => 'bank','mobile' => 'mobile_money'
            };
            $this->ledger->post([
                'business_date' => $date, 'occurred_at' => $payment->paid_at, 'currency' => $payment->currency,
                'source_type' => CustomerSettlement::class, 'source_id' => $payment->id, 'source_event' => 'paid', 'source_number' => $payment->id,
                'idempotency_key' => 'accounting:customer-settlement:'.$payment->id, 'description' => 'Customer payment '.$sale->sale_number, 'posted_by' => $userId,
            ], [
                ['account' => $this->accounts->account($key), 'debit' => (string) $amount, 'stock_location_id' => $sale->stock_location_id],
                ['account' => $this->accounts->account('accounts_receivable'), 'credit' => (string) $amount, 'stock_location_id' => $sale->stock_location_id, 'counterparty_type' => Customer::class, 'counterparty_id' => $customer->id],
            ]);

            return $payment;
        });
    }
}
