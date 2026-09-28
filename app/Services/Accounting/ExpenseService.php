<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    public function post(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId): Expense {
            $existing = Expense::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }

            $expenseAccount = LedgerAccount::query()->where('is_active', true)->findOrFail($data['expense_account_id']);
            $paymentAccount = LedgerAccount::query()->where('is_active', true)->findOrFail($data['payment_account_id']);

            if ($expenseAccount->type !== 'expense') {
                throw ValidationException::withMessages(['expense_account_id' => 'Select an expense account.']);
            }
            if ($paymentAccount->type !== 'asset' || in_array($paymentAccount->system_key, ['accounts_receivable', 'inventory'], true)) {
                throw ValidationException::withMessages(['payment_account_id' => 'Select a cash, bank, mobile, hawala, or other settlement asset account.']);
            }

            $amount = BigDecimal::of((string) $data['amount']);
            if ($amount->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(['amount' => 'Expense amount must be greater than zero.']);
            }

            $expense = Expense::query()->create([
                'expense_number' => 'EXP-'.now()->format('Ymd').'-'.Str::upper(substr((string) Str::ulid(), -10)),
                'expense_account_id' => $expenseAccount->id,
                'payment_account_id' => $paymentAccount->id,
                'stock_location_id' => $data['stock_location_id'] ?? null,
                'business_date' => $data['business_date'],
                'currency' => strtoupper($data['currency'] ?? 'AFN'),
                'amount' => $this->decimal($amount),
                'payee' => $data['payee'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'posted',
                'idempotency_key' => $data['idempotency_key'],
                'created_by' => $userId,
                'posted_at' => now(),
            ]);

            $this->ledger->post([
                'business_date' => $expense->business_date->toDateString(),
                'occurred_at' => $expense->posted_at,
                'currency' => $expense->currency,
                'source_type' => Expense::class,
                'source_id' => $expense->id,
                'source_event' => 'posted',
                'source_number' => $expense->expense_number,
                'idempotency_key' => 'accounting:expense:'.$expense->id,
                'reference' => $expense->reference,
                'description' => 'Expense '.$expense->expense_number.($expense->payee ? ' · '.$expense->payee : ''),
                'posted_by' => $userId,
            ], [
                [
                    'account' => $expenseAccount,
                    'stock_location_id' => $expense->stock_location_id,
                    'debit' => $this->decimal($amount),
                    'credit' => '0.0000',
                    'memo' => $expense->notes ?? 'Operating expense',
                ],
                [
                    'account' => $paymentAccount,
                    'stock_location_id' => $expense->stock_location_id,
                    'debit' => '0.0000',
                    'credit' => $this->decimal($amount),
                    'memo' => 'Expense settlement',
                ],
            ]);

            return $expense->fresh(['expenseAccount', 'paymentAccount', 'location']);
        });
    }

    public function reverse(Expense $expense, string $reason, int $userId): Expense
    {
        return DB::transaction(function () use ($expense, $reason, $userId): Expense {
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($locked->status === 'reversed') {
                return $locked;
            }

            $entry = JournalEntry::query()
                ->where('source_type', Expense::class)
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
