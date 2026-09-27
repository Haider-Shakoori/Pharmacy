<?php

namespace App\Services\DailyClosing;

use App\Models\CashierShift;
use App\Models\DailyClosing;
use App\Models\DailyClosingEvent;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnRefund;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Settings\PharmacySettings;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyClosingService
{
    public function __construct(
        private readonly BusinessDateResolver $businessDates,
        private readonly PharmacySettings $settings,
        private readonly TenantContext $tenantContext,
    ) {}

    public function businessDate(): string
    {
        return $this->businessDates->resolve($this->tenantContext->tenant());
    }

    public function snapshot(StockLocation $location, ?string $businessDate = null): array
    {
        $date = $businessDate ?? $this->businessDate();

        $sales = Sale::query()
            ->where('stock_location_id', $location->id)
            ->whereDate('business_date', $date)
            ->where('status', 'completed');

        $grossSales = BigDecimal::of((string) ($sales->clone()->sum('grand_total') ?? 0));
        $discounts = BigDecimal::of((string) ($sales->clone()->sum('discount_total') ?? 0));
        $credit = BigDecimal::of((string) ($sales->clone()->sum('due_total') ?? 0));
        $change = BigDecimal::of((string) ($sales->clone()->sum('change_total') ?? 0));

        $payments = SalePayment::query()
            ->selectRaw('sale_payments.method, SUM(sale_payments.amount) as total')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.stock_location_id', $location->id)
            ->whereDate('sales.business_date', $date)
            ->where('sales.status', 'completed')
            ->groupBy('sale_payments.method')
            ->pluck('total', 'method');

        $returnTotal = BigDecimal::of((string) SaleReturn::query()
            ->where('stock_location_id', $location->id)
            ->whereDate('business_date', $date)
            ->where('status', 'completed')
            ->sum('refund_total'));

        $refunds = SaleReturnRefund::query()
            ->selectRaw('sale_return_refunds.method, SUM(sale_return_refunds.amount) as total')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_refunds.sale_return_id')
            ->where('sale_returns.stock_location_id', $location->id)
            ->whereDate('sale_returns.business_date', $date)
            ->where('sale_returns.status', 'completed')
            ->groupBy('sale_return_refunds.method')
            ->pluck('total', 'method');

        $cash = BigDecimal::of((string) ($payments['cash'] ?? 0))
            ->minus($change)
            ->minus(BigDecimal::of((string) ($refunds['cash'] ?? 0)));
        $bank = BigDecimal::of((string) ($payments['bank'] ?? 0))
            ->minus(BigDecimal::of((string) ($refunds['bank'] ?? 0)));
        $mobile = BigDecimal::of((string) ($payments['mobile'] ?? 0))
            ->minus(BigDecimal::of((string) ($refunds['mobile'] ?? 0)));
        $credit = $credit->minus(BigDecimal::of((string) ($refunds['credit'] ?? 0)));

        $openingCash = BigDecimal::of((string) CashierShift::query()
            ->where('stock_location_id', $location->id)
            ->whereDate('business_date', $date)
            ->sum('opening_cash'));

        return [
            'business_date' => $date,
            'gross_sales' => $this->decimal($grossSales),
            'discount_total' => $this->decimal($discounts),
            'returns_total' => $this->decimal($returnTotal),
            'cash_collected' => $this->decimal($cash),
            'bank_collected' => $this->decimal($bank),
            'mobile_collected' => $this->decimal($mobile),
            'credit_sales' => $this->decimal($credit),
            'opening_cash' => $this->decimal($openingCash),
            'expected_cash' => $this->decimal($openingCash->plus($cash)),
        ];
    }

    public function openShift(User $user, StockLocation $location, string $openingCash): CashierShift
    {
        $date = $this->businessDate();

        if (CashierShift::query()->where('user_id', $user->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['shift' => 'You already have an open cashier shift.']);
        }

        if ($this->salesBlocked($location, $date)) {
            throw ValidationException::withMessages(['shift' => 'This business day is already finalized.']);
        }

        return CashierShift::query()->create([
            'stock_location_id' => $location->id,
            'user_id' => $user->id,
            'business_date' => $date,
            'status' => 'open',
            'opening_cash' => $this->decimal(BigDecimal::of($openingCash)),
            'opened_at' => now(),
        ]);
    }

    public function closeShift(CashierShift $shift, User $user, string $countedCash, ?string $notes = null): CashierShift
    {
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages(['shift' => 'This shift is already closed.']);
        }

        if ($shift->user_id !== $user->id && ! $user->hasPermission('daily_closing.approve')) {
            abort(403);
        }

        $cash = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.stock_location_id', $shift->stock_location_id)
            ->where('sales.created_by', $shift->user_id)
            ->where('sales.business_date', $shift->business_date->toDateString())
            ->where('sales.status', 'completed')
            ->where('sale_payments.method', 'cash')
            ->where('sale_payments.paid_at', '>=', $shift->opened_at)
            ->sum('sale_payments.amount');

        $change = Sale::query()
            ->where('stock_location_id', $shift->stock_location_id)
            ->where('created_by', $shift->user_id)
            ->where('business_date', $shift->business_date->toDateString())
            ->where('status', 'completed')
            ->where('completed_at', '>=', $shift->opened_at)
            ->sum('change_total');

        $expected = BigDecimal::of($shift->opening_cash)
            ->plus(BigDecimal::of((string) $cash))
            ->minus(BigDecimal::of((string) $change));
        $counted = BigDecimal::of($countedCash);

        $shift->update([
            'status' => 'closed',
            'expected_cash' => $this->decimal($expected),
            'counted_cash' => $this->decimal($counted),
            'variance' => $this->decimal($counted->minus($expected)),
            'closed_at' => now(),
            'closing_notes' => $notes,
        ]);

        return $shift->fresh();
    }

    public function finalize(StockLocation $location, User $user, ?string $countedCash, ?string $notes): DailyClosing
    {
        return DB::transaction(function () use ($location, $user, $countedCash, $notes): DailyClosing {
            $date = $this->businessDate();
            $existing = DailyClosing::query()
                ->where('stock_location_id', $location->id)
                ->whereDate('business_date', $date)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array($existing->status, ['finalized', 'approved'], true)) {
                throw ValidationException::withMessages(['closing' => 'This business day is already finalized.']);
            }

            if (CashierShift::query()->where('stock_location_id', $location->id)->whereDate('business_date', $date)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['closing' => 'Close all cashier shifts before Daily Closing.']);
            }

            $snapshot = $this->snapshot($location, $date);
            $policy = $this->settings->dailyClosing($this->tenantContext->tenant());

            if ($policy['require_counted_cash'] && $countedCash === null) {
                throw ValidationException::withMessages(['counted_cash' => 'Counted cash is required.']);
            }

            $expected = BigDecimal::of($snapshot['expected_cash']);
            $counted = BigDecimal::of($countedCash ?? $snapshot['expected_cash']);
            $variance = $counted->minus($expected);

            if (abs((float) (string) $variance) > (float) $policy['variance_note_threshold'] && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'A note is required for this cash variance.']);
            }

            $closing = $existing ?? new DailyClosing([
                'stock_location_id' => $location->id,
                'business_date' => $date,
            ]);

            $closing->fill([
                ...$snapshot,
                'status' => 'finalized',
                'counted_cash' => $this->decimal($counted),
                'variance' => $this->decimal($variance),
                'finalized_by' => $user->id,
                'finalized_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'closing_notes' => $notes,
            ])->save();

            $this->event($closing, 'finalized', $user, $notes, $snapshot);

            return $closing;
        });
    }

    public function approve(DailyClosing $closing, User $user): DailyClosing
    {
        if ($closing->status !== 'finalized') {
            throw ValidationException::withMessages(['closing' => 'Only a finalized Daily Closing can be approved.']);
        }

        $closing->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        $this->event($closing, 'approved', $user);

        return $closing->fresh();
    }

    public function reopen(DailyClosing $closing, User $user, string $reason): DailyClosing
    {
        $policy = $this->settings->dailyClosing($this->tenantContext->tenant());

        if (! $policy['allow_reopen']) {
            throw ValidationException::withMessages(['closing' => 'Reopening Daily Closing is disabled in pharmacy settings.']);
        }

        if (! in_array($closing->status, ['finalized', 'approved'], true)) {
            throw ValidationException::withMessages(['closing' => 'This Daily Closing is not finalized.']);
        }

        $closing->update([
            'status' => 'reopened',
            'reopened_by' => $user->id,
            'reopened_at' => now(),
            'reopen_reason' => $reason,
        ]);
        $this->event($closing, 'reopened', $user, $reason);

        return $closing->fresh();
    }

    public function salesBlocked(StockLocation $location, ?string $businessDate = null): bool
    {
        $policy = $this->settings->dailyClosing($this->tenantContext->tenant());

        if (! $policy['block_online_sales_after_close']) {
            return false;
        }

        return DailyClosing::query()
            ->where('stock_location_id', $location->id)
            ->whereDate('business_date', $businessDate ?? $this->businessDate())
            ->whereIn('status', ['finalized', 'approved'])
            ->exists();
    }

    private function event(DailyClosing $closing, string $type, User $user, ?string $reason = null, ?array $snapshot = null): void
    {
        DailyClosingEvent::query()->create([
            'daily_closing_id' => $closing->id,
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
