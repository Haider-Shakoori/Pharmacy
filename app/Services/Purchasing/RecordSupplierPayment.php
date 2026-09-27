<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseInvoice;
use App\Models\SupplierPayment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordSupplierPayment
{
    public function record(PurchaseInvoice $invoice, array $data, int $userId): SupplierPayment
    {
        return DB::transaction(function () use ($invoice, $data, $userId): SupplierPayment {
            $locked = PurchaseInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! empty($data['idempotency_key'])) {
                $existing = SupplierPayment::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'Cancelled invoices cannot receive payments.']);
            }

            if (strtoupper($data['currency']) !== $locked->currency) {
                throw ValidationException::withMessages(['currency' => 'Payment currency must match invoice currency.']);
            }

            $amount = BigDecimal::of((string) $data['amount']);
            $balance = BigDecimal::of($locked->balance_due);

            if ($amount->isLessThanOrEqualTo(BigDecimal::zero()) || $amount->isGreaterThan($balance)) {
                throw ValidationException::withMessages(['amount' => 'Payment must be greater than zero and cannot exceed the balance due.']);
            }

            $payment = SupplierPayment::query()->create([
                ...$data,
                'supplier_id' => $locked->supplier_id,
                'purchase_invoice_id' => $locked->id,
                'payment_number' => 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::ulid()->toBase32()),
                'created_by' => $userId,
            ]);

            $paid = BigDecimal::of($locked->paid_total)->plus($amount);
            $newBalance = BigDecimal::of($locked->grand_total)->minus($paid);

            $locked->update([
                'paid_total' => (string) $paid->toScale(4, RoundingMode::HalfUp),
                'balance_due' => (string) $newBalance->toScale(4, RoundingMode::HalfUp),
                'status' => $newBalance->isZero() ? 'paid' : 'partially_paid',
            ]);

            return $payment;
        });
    }
}
