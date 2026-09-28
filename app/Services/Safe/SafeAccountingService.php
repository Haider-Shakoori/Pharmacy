<?php

namespace App\Services\Safe;

use App\Models\CashSafe;
use App\Models\JournalEntry;
use App\Models\SafeClosing;
use App\Models\SafeClosingEvent;
use App\Models\SafeMovement;
use App\Services\Accounting\AccountingProvisioner;
use App\Services\Accounting\LedgerPostingService;
use Brick\Math\BigDecimal;

class SafeAccountingService
{
    public function __construct(
        private readonly AccountingProvisioner $accounts,
        private readonly LedgerPostingService $ledger,
    ) {}

    public function postMovement(SafeMovement $movement): ?JournalEntry
    {
        $safe = $movement->safe()->with('location')->firstOrFail();
        $amount = BigDecimal::of($movement->amount);
        $lines = match ($movement->movement_type) {
            'owner_deposit' => [
                $this->line('cash_on_hand', $safe, $amount, true, 'Owner cash deposited into safe'),
                $this->line('owner_equity', $safe, $amount, false, 'Owner contribution'),
            ],
            'owner_withdrawal' => [
                $this->line('owner_drawings', $safe, $amount, true, 'Owner cash withdrawal'),
                $this->line('cash_on_hand', $safe, $amount, false, 'Cash removed from safe'),
            ],
            'bank_deposit' => [
                $this->line('bank', $safe, $amount, true, 'Cash deposited to bank'),
                $this->line('cash_on_hand', $safe, $amount, false, 'Cash removed from safe for bank deposit'),
            ],
            'bank_withdrawal' => [
                $this->line('cash_on_hand', $safe, $amount, true, 'Cash received from bank'),
                $this->line('bank', $safe, $amount, false, 'Bank withdrawal into safe'),
            ],
            default => [],
        };

        if ($lines === []) {
            return null;
        }

        return $this->ledger->post([
            'business_date' => $movement->business_date->toDateString(),
            'occurred_at' => $movement->occurred_at,
            'currency' => 'AFN',
            'source_type' => SafeMovement::class,
            'source_id' => $movement->id,
            'source_event' => $movement->movement_type,
            'source_number' => $safe->code,
            'idempotency_key' => 'accounting:safe-movement:'.$movement->id,
            'reference' => $movement->reference,
            'description' => 'Safe movement '.$movement->movement_type.' · '.$safe->name,
            'posted_by' => $movement->created_by,
        ], $lines);
    }

    public function postClosingVariance(SafeClosing $closing, SafeClosingEvent $event): ?JournalEntry
    {
        $variance = BigDecimal::of($closing->variance ?? '0');
        if ($variance->isZero()) {
            return null;
        }

        $safe = $closing->safe()->with('location')->firstOrFail();
        $amount = $variance->abs();
        $lines = $variance->isPositive()
            ? [
                $this->line('cash_on_hand', $safe, $amount, true, 'Safe cash over'),
                $this->line('cash_over_short', $safe, $amount, false, 'Safe cash over'),
            ]
            : [
                $this->line('cash_over_short', $safe, $amount, true, 'Safe cash shortage'),
                $this->line('cash_on_hand', $safe, $amount, false, 'Safe cash shortage'),
            ];

        return $this->ledger->post([
            'business_date' => $closing->business_date->toDateString(),
            'occurred_at' => $event->occurred_at,
            'currency' => 'AFN',
            'source_type' => SafeClosing::class,
            'source_id' => $closing->id,
            'source_event' => 'variance:'.$event->id,
            'source_number' => $safe->code.'-'.$closing->business_date->format('Ymd'),
            'idempotency_key' => 'accounting:safe-closing-variance:'.$event->id,
            'description' => 'Safe closing variance · '.$safe->name,
            'posted_by' => $event->actor_id,
        ], $lines);
    }

    public function reverseClosingVariance(SafeClosing $closing, string $reason, ?int $actorId): void
    {
        JournalEntry::query()
            ->where('source_type', SafeClosing::class)
            ->where('source_id', $closing->id)
            ->where('source_event', 'like', 'variance:%')
            ->where('status', 'posted')
            ->get()
            ->each(fn (JournalEntry $entry) => $this->ledger->reverse($entry, $reason, $actorId));
    }

    private function line(string $accountKey, CashSafe $safe, BigDecimal $amount, bool $debit, string $memo): array
    {
        return [
            'account' => $this->accounts->account($accountKey),
            'stock_location_id' => $safe->stock_location_id,
            'debit' => $debit ? (string) $amount : '0.0000',
            'credit' => $debit ? '0.0000' : (string) $amount,
            'memo' => $memo,
        ];
    }
}
