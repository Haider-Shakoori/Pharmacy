<?php

namespace App\Services\Accounting;

use App\Models\AccountingAdjustment;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingAdjustmentService
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    public function post(array $data, int $userId): AccountingAdjustment
    {
        return DB::transaction(function () use ($data, $userId): AccountingAdjustment {
            $existing = AccountingAdjustment::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }

            if ($data['debit_account_id'] === $data['credit_account_id']) {
                throw ValidationException::withMessages(['credit_account_id' => 'Debit and credit accounts must be different.']);
            }

            $debit = LedgerAccount::query()->where('is_active', true)->findOrFail($data['debit_account_id']);
            $credit = LedgerAccount::query()->where('is_active', true)->findOrFail($data['credit_account_id']);
            $amount = BigDecimal::of((string) $data['amount']);

            if ($amount->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(['amount' => 'Adjustment amount must be greater than zero.']);
            }

            $adjustment = AccountingAdjustment::query()->create([
                'adjustment_number' => 'AJE-'.now()->format('Ymd').'-'.Str::upper(substr((string) Str::ulid(), -10)),
                'business_date' => $data['business_date'],
                'currency' => strtoupper($data['currency'] ?? 'AFN'),
                'debit_account_id' => $debit->id,
                'credit_account_id' => $credit->id,
                'stock_location_id' => $data['stock_location_id'] ?? null,
                'amount' => $this->decimal($amount),
                'reference' => $data['reference'] ?? null,
                'reason' => $data['reason'],
                'status' => 'posted',
                'idempotency_key' => $data['idempotency_key'],
                'created_by' => $userId,
                'posted_at' => now(),
            ]);

            $this->ledger->post([
                'business_date' => $adjustment->business_date->toDateString(),
                'occurred_at' => $adjustment->posted_at,
                'currency' => $adjustment->currency,
                'source_type' => AccountingAdjustment::class,
                'source_id' => $adjustment->id,
                'source_event' => 'posted',
                'source_number' => $adjustment->adjustment_number,
                'idempotency_key' => 'accounting:adjustment:'.$adjustment->id,
                'reference' => $adjustment->reference,
                'description' => 'Accounting adjustment '.$adjustment->adjustment_number,
                'posted_by' => $userId,
            ], [
                [
                    'account' => $debit,
                    'stock_location_id' => $adjustment->stock_location_id,
                    'debit' => $this->decimal($amount),
                    'credit' => '0.0000',
                    'memo' => $adjustment->reason,
                ],
                [
                    'account' => $credit,
                    'stock_location_id' => $adjustment->stock_location_id,
                    'debit' => '0.0000',
                    'credit' => $this->decimal($amount),
                    'memo' => $adjustment->reason,
                ],
            ]);

            return $adjustment->fresh(['debitAccount', 'creditAccount', 'location']);
        });
    }

    public function reverse(AccountingAdjustment $adjustment, string $reason, int $userId): AccountingAdjustment
    {
        return DB::transaction(function () use ($adjustment, $reason, $userId): AccountingAdjustment {
            $locked = AccountingAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            if ($locked->status === 'reversed') {
                return $locked;
            }

            $entry = JournalEntry::query()
                ->where('source_type', AccountingAdjustment::class)
                ->where('source_id', $locked->id)
                ->where('source_event', 'posted')
                ->where('status', 'posted')
                ->firstOrFail();

            $this->ledger->reverse($entry, $reason, $userId);
            $locked->update(['status' => 'reversed', 'reversed_at' => now()]);

            return $locked->fresh();
        });
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
