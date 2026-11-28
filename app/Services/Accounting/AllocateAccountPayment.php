<?php

namespace App\Services\Accounting;

use App\Models\Customer;
use App\Models\CustomerSettlement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Purchasing\RecordSupplierPayment;
use App\Services\Sales\RecordCustomerSettlement;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocateAccountPayment
{
    public function __construct(private RecordCustomerSettlement $customers, private RecordSupplierPayment $suppliers) {}

    public function record(Customer|Supplier $party, array $data, int $userId): array
    {
        return DB::transaction(function () use ($party, $data, $userId) {
            $party = $party->newQuery()->lockForUpdate()->findOrFail($party->id);
            $customer = $party instanceof Customer;
            $key = $data['idempotency_key'];
            $model = $customer ? CustomerSettlement::class : SupplierPayment::class;
            $firstKey = $this->allocationKey($key, 0);
            $existing = $model::query()->where('idempotency_key', $firstKey)->first();
            if ($existing) {
                abort_unless((int) $existing->created_by === $userId && ($customer ? $party->sales()->whereKey($existing->sale_id)->exists() : $existing->supplier_id === $party->id), 409);
                $payments = [];
                for ($i = 0; $i < 10000; $i++) {
                    $item = $model::query()->where('idempotency_key', $this->allocationKey($key, $i))->first();
                    if (! $item) {
                        break;
                    }
                    $payments[] = $item;
                }

                return $payments;
            }
            $column = $customer ? 'due_total' : 'balance_due';
            $query = $customer ? $party->sales()->where('status', 'completed') : $party->invoices()->whereNotIn('status', ['cancelled', 'paid']);
            $invoices = $query->where('currency', $data['currency'])->where($column, '>', 0)->orderBy($customer ? 'business_date' : 'invoice_date')->orderBy('id')->lockForUpdate()->get();
            $remaining = BigDecimal::of((string) $data['amount'])->toScale(4);
            $total = $invoices->reduce(fn ($sum, $invoice) => $sum->plus($invoice->$column), BigDecimal::zero());
            if (! $remaining->isPositive() || $remaining->isGreaterThan($total)) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds outstanding balance.']);
            }
            $payments = [];
            foreach ($invoices as $index => $invoice) {
                if ($remaining->isZero()) {
                    break;
                }
                $due = BigDecimal::of($invoice->$column);
                $amount = $remaining->isGreaterThan($due) ? $due : $remaining;
                $allocation = [...$data, 'amount' => (string) $amount, 'idempotency_key' => $this->allocationKey($key, $index)];
                if ($customer) {
                    unset($allocation['currency']);
                    $payments[] = $this->customers->record($party, $invoice->id, $allocation, $userId);
                } else {
                    $payments[] = $this->suppliers->record($invoice, [...$allocation, 'paid_at' => now()], $userId);
                }
                $remaining = $remaining->minus($amount);
            }

            return $payments;
        });
    }

    private function allocationKey(string $key, int $index): string
    {
        $hash = hash('sha256', 'account-allocation:'.$key.':'.$index);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
}
