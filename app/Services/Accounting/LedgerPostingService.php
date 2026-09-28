<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LedgerPostingService
{
    public function post(array $entryData, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($entryData, $lines): JournalEntry {
            $existing = JournalEntry::query()
                ->where('idempotency_key', $entryData['idempotency_key'])
                ->first();

            if ($existing) {
                return $existing->load('lines.account');
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['journal' => 'A journal entry requires at least one line.']);
            }

            $debitTotal = BigDecimal::zero();
            $creditTotal = BigDecimal::zero();
            $normalized = [];

            foreach ($lines as $index => $line) {
                $providedAccount = $line['account'] ?? null;
                $account = $providedAccount instanceof LedgerAccount
                    ? $providedAccount
                    : LedgerAccount::query()->where('is_active', true)->findOrFail($line['account_id']);
                $debit = BigDecimal::of((string) ($line['debit'] ?? '0'));
                $credit = BigDecimal::of((string) ($line['credit'] ?? '0'));

                if ($debit->isNegative() || $credit->isNegative()) {
                    throw ValidationException::withMessages(["journal.$index" => 'Journal amounts cannot be negative.']);
                }

                if (($debit->isZero() && $credit->isZero()) || (! $debit->isZero() && ! $credit->isZero())) {
                    throw ValidationException::withMessages(["journal.$index" => 'Each journal line must contain either a debit or a credit.']);
                }

                $debitTotal = $debitTotal->plus($debit);
                $creditTotal = $creditTotal->plus($credit);
                $normalized[] = [
                    'ledger_account_id' => $account->id,
                    'stock_location_id' => $line['stock_location_id'] ?? null,
                    'debit' => $this->decimal($debit),
                    'credit' => $this->decimal($credit),
                    'counterparty_type' => $line['counterparty_type'] ?? null,
                    'counterparty_id' => $line['counterparty_id'] ?? null,
                    'memo' => $line['memo'] ?? null,
                ];
            }

            if (! $debitTotal->isEqualTo($creditTotal)) {
                throw ValidationException::withMessages([
                    'journal' => 'Journal entry is not balanced.',
                ]);
            }

            $entry = JournalEntry::query()->create([
                ...$entryData,
                'journal_number' => $entryData['journal_number'] ?? 'JE-'.now()->format('Ymd').'-'.Str::upper(substr((string) Str::ulid(), -10)),
                'status' => 'posted',
                'currency' => strtoupper($entryData['currency'] ?? 'AFN'),
                'occurred_at' => $entryData['occurred_at'] ?? now(),
                'total_debit' => $this->decimal($debitTotal),
                'total_credit' => $this->decimal($creditTotal),
                'posted_at' => $entryData['posted_at'] ?? now(),
            ]);

            $entry->lines()->createMany($normalized);

            return $entry->fresh('lines.account');
        });
    }

    public function reverse(JournalEntry $entry, string $reason, ?int $actorId = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reason, $actorId): JournalEntry {
            $original = JournalEntry::query()
                ->with('lines.account')
                ->lockForUpdate()
                ->findOrFail($entry->id);

            if ($original->status === 'reversed') {
                return $original->reversal()->with('lines.account')->firstOrFail();
            }

            $reversal = $this->post([
                'business_date' => now()->toDateString(),
                'occurred_at' => now(),
                'currency' => $original->currency,
                'source_type' => $original->source_type,
                'source_id' => $original->source_id,
                'source_event' => 'reversal',
                'source_number' => $original->source_number,
                'idempotency_key' => 'accounting:reversal:'.$original->id,
                'reference' => $original->journal_number,
                'description' => 'Reversal of '.$original->journal_number.': '.$reason,
                'posted_by' => $actorId,
                'reversal_of_id' => $original->id,
                'reversal_reason' => $reason,
            ], $original->lines->map(fn ($line) => [
                'account_id' => $line->ledger_account_id,
                'stock_location_id' => $line->stock_location_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'counterparty_type' => $line->counterparty_type,
                'counterparty_id' => $line->counterparty_id,
                'memo' => 'Reversal: '.($line->memo ?? $original->description),
            ])->all());

            $original->update([
                'status' => 'reversed',
                'reversal_reason' => $reason,
            ]);

            return $reversal;
        });
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
