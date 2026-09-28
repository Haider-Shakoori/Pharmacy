<?php

namespace App\Services\Safe;

use App\Models\CashierShift;
use App\Models\CashSafe;
use App\Models\DailyClosing;
use App\Models\SafeClosing;
use App\Models\SafeClosingEvent;
use App\Models\SafeMovement;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\DailyClosing\BusinessDateResolver;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SafeService
{
    public const MANUAL_TYPES = ['owner_deposit', 'owner_withdrawal', 'bank_deposit', 'bank_withdrawal'];

    public function __construct(
        private readonly BusinessDateResolver $businessDates,
        private readonly TenantContext $tenantContext,
        private readonly SafeAccountingService $accounting,
    ) {}

    public function businessDate(): string
    {
        return $this->businessDates->resolve($this->tenantContext->tenant());
    }

    public function ensureDefaultSafe(): CashSafe
    {
        $location = StockLocation::query()->where('is_default', true)->where('is_active', true)->firstOrFail();

        return CashSafe::query()->firstOrCreate(
            ['code' => 'MAIN-SAFE'],
            [
                'stock_location_id' => $location->id,
                'name' => 'Main Cash Safe',
                'is_default' => true,
                'is_active' => true,
                'notes' => 'Primary physical cash safe for this pharmacy.',
            ],
        );
    }

    public function balance(CashSafe $safe): string
    {
        $incoming = BigDecimal::of((string) ($safe->movements()->where('direction', 'in')->sum('amount') ?? 0));
        $outgoing = BigDecimal::of((string) ($safe->movements()->where('direction', 'out')->sum('amount') ?? 0));

        return $this->decimal($incoming->minus($outgoing));
    }

    public function snapshot(CashSafe $safe, ?string $businessDate = null): array
    {
        $date = $businessDate ?? $this->businessDate();
        $openingIn = BigDecimal::of((string) ($safe->movements()->whereDate('business_date', '<', $date)->where('direction', 'in')->sum('amount') ?? 0));
        $openingOut = BigDecimal::of((string) ($safe->movements()->whereDate('business_date', '<', $date)->where('direction', 'out')->sum('amount') ?? 0));
        $opening = $openingIn->minus($openingOut);

        $base = $safe->movements()
            ->whereDate('business_date', $date)
            ->whereNotIn('movement_type', ['closing_variance', 'closing_variance_reversal']);
        $cashIn = BigDecimal::of((string) ($base->clone()->where('direction', 'in')->sum('amount') ?? 0));
        $cashOut = BigDecimal::of((string) ($base->clone()->where('direction', 'out')->sum('amount') ?? 0));
        $expected = $opening->plus($cashIn)->minus($cashOut);

        return [
            'business_date' => $date,
            'opening_balance' => $this->decimal($opening),
            'cash_in' => $this->decimal($cashIn),
            'cash_out' => $this->decimal($cashOut),
            'expected_balance' => $this->decimal($expected),
        ];
    }

    public function pendingShiftTransfers(CashSafe $safe, ?string $businessDate = null): Collection
    {
        $date = $businessDate ?? $this->businessDate();

        return CashierShift::query()
            ->with('user:id,name')
            ->where('stock_location_id', $safe->stock_location_id)
            ->whereDate('business_date', $date)
            ->where('status', 'closed')
            ->orderBy('closed_at')
            ->get()
            ->map(function (CashierShift $shift): array {
                $transferred = BigDecimal::of((string) (SafeMovement::query()
                    ->where('movement_type', 'pos_transfer')
                    ->where('source_type', CashierShift::class)
                    ->where('source_id', $shift->id)
                    ->sum('amount') ?? 0));
                $counted = BigDecimal::of($shift->counted_cash ?? '0');
                $opening = BigDecimal::of($shift->opening_cash ?? '0');
                $transferable = $counted->minus($opening)->minus($transferred);
                if ($transferable->isNegative()) {
                    $transferable = BigDecimal::zero();
                }

                return [
                    'shift' => $shift,
                    'transferred' => $this->decimal($transferred),
                    'remaining' => $this->decimal($transferable),
                    'suggested' => $this->decimal($transferable),
                ];
            })
            ->filter(fn (array $row) => BigDecimal::of($row['remaining'])->isPositive())
            ->values();
    }

    public function receiveFromShift(CashSafe $safe, CashierShift $shift, User $user, string $amount, string $idempotencyKey): SafeMovement
    {
        return DB::transaction(function () use ($safe, $shift, $user, $amount, $idempotencyKey): SafeMovement {
            if ($existing = SafeMovement::query()->where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }

            $shift = CashierShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($shift->status !== 'closed') {
                throw ValidationException::withMessages(['shift' => 'Close the cashier shift before transferring cash to the safe.']);
            }
            if ($shift->stock_location_id !== $safe->stock_location_id) {
                throw ValidationException::withMessages(['shift' => 'This cashier shift belongs to a different location.']);
            }
            if ($shift->user_id !== $user->id && ! $user->hasPermission('safe.manage')) {
                abort(403);
            }

            $this->assertDateOpen($safe, $shift->business_date->toDateString());
            $value = BigDecimal::of($amount);
            if (! $value->isPositive()) {
                throw ValidationException::withMessages(['amount' => 'Transfer amount must be greater than zero.']);
            }

            $transferred = BigDecimal::of((string) (SafeMovement::query()
                ->where('movement_type', 'pos_transfer')
                ->where('source_type', CashierShift::class)
                ->where('source_id', $shift->id)
                ->sum('amount') ?? 0));
            $remaining = BigDecimal::of($shift->counted_cash ?? '0')
                ->minus(BigDecimal::of($shift->opening_cash ?? '0'))
                ->minus($transferred);
            if ($remaining->isNegative()) {
                $remaining = BigDecimal::zero();
            }
            if ($value->isGreaterThan($remaining)) {
                throw ValidationException::withMessages(['amount' => 'Transfer exceeds the remaining POS cash available after preserving the opening float.']);
            }

            return SafeMovement::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], [
                'cash_safe_id' => $safe->id,
                'business_date' => $shift->business_date,
                'direction' => 'in',
                'movement_type' => 'pos_transfer',
                'amount' => $this->decimal($value),
                'source_type' => CashierShift::class,
                'source_id' => $shift->id,
                'reference' => 'POS '.$shift->business_date->toDateString().' · '.$shift->user?->name,
                'reason' => 'Cash transferred from closed POS cashier shift.',
                'created_by' => $user->id,
                'occurred_at' => now(),
            ]);
        });
    }

    public function postManual(CashSafe $safe, User $user, array $data): SafeMovement
    {
        return DB::transaction(function () use ($safe, $user, $data): SafeMovement {
            if ($existing = SafeMovement::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }

            if (! in_array($data['movement_type'], self::MANUAL_TYPES, true)) {
                throw ValidationException::withMessages(['movement_type' => 'Unsupported safe movement type.']);
            }

            $date = $this->businessDate();
            $this->assertDateOpen($safe, $date);
            $amount = BigDecimal::of((string) $data['amount']);
            if (! $amount->isPositive()) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }

            $direction = in_array($data['movement_type'], ['owner_deposit', 'bank_withdrawal'], true) ? 'in' : 'out';
            if ($direction === 'out' && $amount->isGreaterThan(BigDecimal::of($this->balance($safe)))) {
                throw ValidationException::withMessages(['amount' => 'Safe balance is not sufficient for this cash-out movement.']);
            }

            $movement = SafeMovement::query()->firstOrCreate(['idempotency_key' => $data['idempotency_key']], [
                'cash_safe_id' => $safe->id,
                'business_date' => $date,
                'direction' => $direction,
                'movement_type' => $data['movement_type'],
                'amount' => $this->decimal($amount),
                'reference' => $data['reference'] ?? null,
                'reason' => $data['reason'],
                'created_by' => $user->id,
                'occurred_at' => now(),
            ]);

            $this->accounting->postMovement($movement);

            return $movement;
        });
    }

    public function finalize(CashSafe $safe, User $user, string $countedBalance, ?string $notes): SafeClosing
    {
        return DB::transaction(function () use ($safe, $user, $countedBalance, $notes): SafeClosing {
            $date = $this->businessDate();
            $existing = SafeClosing::query()->where('cash_safe_id', $safe->id)->whereDate('business_date', $date)->lockForUpdate()->first();
            if ($existing && in_array($existing->status, ['finalized', 'approved'], true)) {
                throw ValidationException::withMessages(['closing' => 'This safe is already closed for the business date.']);
            }

            if (CashierShift::query()->where('stock_location_id', $safe->stock_location_id)->whereDate('business_date', $date)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['closing' => 'Close all POS cashier shifts before Safe Closing.']);
            }

            if ($this->pendingShiftTransfers($safe, $date)->isNotEmpty()) {
                throw ValidationException::withMessages(['closing' => 'Transfer all closed POS cash into the safe before Safe Closing.']);
            }

            if (! DailyClosing::query()->where('stock_location_id', $safe->stock_location_id)->whereDate('business_date', $date)->whereIn('status', ['finalized', 'approved'])->exists()) {
                throw ValidationException::withMessages(['closing' => 'Finalize Daily Closing for this location before Safe Closing.']);
            }

            $snapshot = $this->snapshot($safe, $date);
            $counted = BigDecimal::of($countedBalance);
            if ($counted->isNegative()) {
                throw ValidationException::withMessages(['counted_balance' => 'Counted safe balance cannot be negative.']);
            }
            $expected = BigDecimal::of($snapshot['expected_balance']);
            $variance = $counted->minus($expected);
            if (! $variance->isZero() && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'A note is required when the safe count differs from the expected balance.']);
            }

            $closing = $existing ?? new SafeClosing(['cash_safe_id' => $safe->id, 'business_date' => $date]);
            $closing->fill([
                ...$snapshot,
                'status' => 'finalized',
                'counted_balance' => $this->decimal($counted),
                'variance' => $this->decimal($variance),
                'finalized_by' => $user->id,
                'finalized_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'closing_notes' => $notes,
            ])->save();

            $event = $this->event($closing, 'finalized', $user, $notes, $snapshot);
            if (! $variance->isZero()) {
                SafeMovement::query()->create([
                    'cash_safe_id' => $safe->id,
                    'business_date' => $date,
                    'direction' => $variance->isPositive() ? 'in' : 'out',
                    'movement_type' => 'closing_variance',
                    'amount' => $this->decimal($variance->abs()),
                    'source_type' => SafeClosing::class,
                    'source_id' => $closing->id,
                    'reference' => $event->id,
                    'reason' => 'Safe closing count variance.',
                    'idempotency_key' => 'safe:closing-variance:'.$event->id,
                    'created_by' => $user->id,
                    'occurred_at' => now(),
                ]);
                $this->accounting->postClosingVariance($closing, $event);
            }

            return $closing->fresh(['events.actor']);
        });
    }

    public function approve(SafeClosing $closing, User $user): SafeClosing
    {
        if ($closing->status !== 'finalized') {
            throw ValidationException::withMessages(['closing' => 'Only a finalized safe closing can be approved.']);
        }

        $closing->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->event($closing, 'approved', $user);

        return $closing->fresh();
    }

    public function reopen(SafeClosing $closing, User $user, string $reason): SafeClosing
    {
        return DB::transaction(function () use ($closing, $user, $reason): SafeClosing {
            if (! in_array($closing->status, ['finalized', 'approved'], true)) {
                throw ValidationException::withMessages(['closing' => 'This safe closing is not finalized.']);
            }

            $varianceMovements = SafeMovement::query()
                ->where('cash_safe_id', $closing->cash_safe_id)
                ->where('movement_type', 'closing_variance')
                ->where('source_type', SafeClosing::class)
                ->where('source_id', $closing->id)
                ->get();

            foreach ($varianceMovements as $movement) {
                $alreadyReversed = SafeMovement::query()
                    ->where('movement_type', 'closing_variance_reversal')
                    ->where('source_type', SafeMovement::class)
                    ->where('source_id', $movement->id)
                    ->exists();
                if (! $alreadyReversed) {
                    SafeMovement::query()->create([
                        'cash_safe_id' => $closing->cash_safe_id,
                        'business_date' => $closing->business_date,
                        'direction' => $movement->direction === 'in' ? 'out' : 'in',
                        'movement_type' => 'closing_variance_reversal',
                        'amount' => $movement->amount,
                        'source_type' => SafeMovement::class,
                        'source_id' => $movement->id,
                        'reference' => $closing->id,
                        'reason' => 'Safe closing reopened: '.$reason,
                        'idempotency_key' => 'safe:closing-variance-reversal:'.$movement->id,
                        'created_by' => $user->id,
                        'occurred_at' => now(),
                    ]);
                }
            }

            $closing->update([
                'status' => 'reopened',
                'reopened_by' => $user->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ]);
            $this->event($closing, 'reopened', $user, $reason);
            $this->accounting->reverseClosingVariance($closing, 'Safe closing reopened: '.$reason, $user->id);

            return $closing->fresh();
        });
    }

    private function assertDateOpen(CashSafe $safe, string $date): void
    {
        if (SafeClosing::query()->where('cash_safe_id', $safe->id)->whereDate('business_date', $date)->whereIn('status', ['finalized', 'approved'])->exists()) {
            throw ValidationException::withMessages(['safe' => 'This safe is closed for the selected business date. Reopen it before posting cash movements.']);
        }
    }

    private function event(SafeClosing $closing, string $type, User $user, ?string $reason = null, ?array $snapshot = null): SafeClosingEvent
    {
        return SafeClosingEvent::query()->create([
            'safe_closing_id' => $closing->id,
            'event_type' => $type,
            'actor_id' => $user->id,
            'reason' => $reason,
            'snapshot' => $snapshot,
            'occurred_at' => now(),
        ]);
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
