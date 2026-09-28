<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\Sale;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountingReportService
{
    public function __construct(private readonly AccountingProvisioner $provisioner) {}

    public function range(?string $from, ?string $to): array
    {
        $end = $to ? CarbonImmutable::parse($to)->startOfDay() : today()->toImmutable();
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $end->startOfMonth();

        if ($start->isAfter($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    public function trialBalance(string $from, string $to): Collection
    {
        $this->provisioner->ensureDefaults();

        $totals = JournalLine::query()
            ->selectRaw('ledger_account_id, COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')
            ->whereHas('entry', fn (Builder $query) => $query
                ->whereDate('business_date', '>=', $from)
                ->whereDate('business_date', '<=', $to))
            ->groupBy('ledger_account_id')
            ->get()
            ->keyBy('ledger_account_id');

        return LedgerAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (LedgerAccount $account) use ($totals): array {
                $row = $totals->get($account->id);
                $debit = BigDecimal::of((string) ($row?->debit_total ?? 0));
                $credit = BigDecimal::of((string) ($row?->credit_total ?? 0));
                $balance = $account->normal_balance === 'credit'
                    ? $credit->minus($debit)
                    : $debit->minus($credit);

                return [
                    'account' => $account,
                    'debit' => $this->decimal($debit),
                    'credit' => $this->decimal($credit),
                    'balance' => $this->decimal($balance),
                ];
            });
    }

    public function profitAndLoss(string $from, string $to): array
    {
        $trial = $this->trialBalance($from, $to);
        $revenue = BigDecimal::zero();
        $expenses = BigDecimal::zero();

        foreach ($trial as $row) {
            $account = $row['account'];
            $debit = BigDecimal::of($row['debit']);
            $credit = BigDecimal::of($row['credit']);

            if ($account->type === 'revenue') {
                $revenue = $revenue->plus($credit->minus($debit));
            } elseif ($account->type === 'expense') {
                $expenses = $expenses->plus($debit->minus($credit));
            }
        }

        return [
            'net_revenue' => $this->decimal($revenue),
            'expenses' => $this->decimal($expenses),
            'net_income' => $this->decimal($revenue->minus($expenses)),
        ];
    }

    public function keyBalances(): array
    {
        $this->provisioner->ensureDefaults();

        $keys = [
            'cash_on_hand', 'bank', 'mobile_money', 'hawala_clearing',
            'accounts_receivable', 'inventory', 'accounts_payable',
        ];

        $accounts = LedgerAccount::query()->whereIn('system_key', $keys)->get()->keyBy('system_key');
        $result = [];

        foreach ($keys as $key) {
            $account = $accounts->get($key);
            if (! $account) {
                $result[$key] = '0.0000';

                continue;
            }

            $totals = JournalLine::query()
                ->where('ledger_account_id', $account->id)
                ->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')
                ->first();
            $debit = BigDecimal::of((string) ($totals->debit_total ?? 0));
            $credit = BigDecimal::of((string) ($totals->credit_total ?? 0));
            $balance = $account->normal_balance === 'credit'
                ? $credit->minus($debit)
                : $debit->minus($credit);
            $result[$key] = $this->decimal($balance);
        }

        return $result;
    }

    public function inventoryReconciliation(): array
    {
        $ledgerInventory = BigDecimal::of($this->keyBalances()['inventory']);
        $batchValue = BigDecimal::of((string) ProductBatch::query()
            ->where('available_quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(available_quantity * purchase_cost), 0) as total')
            ->value('total'));

        return [
            'ledger_inventory' => $this->decimal($ledgerInventory),
            'batch_stock_value' => $this->decimal($batchValue),
            'difference' => $this->decimal($ledgerInventory->minus($batchValue)),
        ];
    }

    public function journals(string $from, string $to, ?string $accountId = null)
    {
        return JournalEntry::query()
            ->with(['lines.account', 'poster:id,name'])
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->when($accountId, fn (Builder $query) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('ledger_account_id', $accountId)))
            ->latest('occurred_at')
            ->paginate(30, ['*'], 'journals_page')
            ->withQueryString();
    }

    public function receivableAging(): array
    {
        $today = today()->toImmutable();
        $buckets = $this->emptyAging();

        Sale::query()
            ->with('customer:id,name')
            ->where('status', 'completed')
            ->where('due_total', '>', 0)
            ->get()
            ->each(function (Sale $sale) use (&$buckets, $today): void {
                $days = max(0, $sale->business_date->diffInDays($today));
                $bucket = $this->agingBucket($days);
                $buckets[$bucket] = BigDecimal::of($buckets[$bucket])->plus(BigDecimal::of($sale->due_total));
            });

        return $this->scaleAging($buckets);
    }

    public function payableAging(): array
    {
        $today = today()->toImmutable();
        $buckets = $this->emptyAging();

        PurchaseInvoice::query()
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->get()
            ->each(function (PurchaseInvoice $invoice) use (&$buckets, $today): void {
                $date = $invoice->due_date?->toImmutable() ?? $invoice->invoice_date->toImmutable();
                $days = max(0, $date->diffInDays($today));
                $bucket = $this->agingBucket($days);
                $buckets[$bucket] = BigDecimal::of($buckets[$bucket])->plus(BigDecimal::of($invoice->balance_due));
            });

        return $this->scaleAging($buckets);
    }

    private function emptyAging(): array
    {
        return ['current' => '0', '1_30' => '0', '31_60' => '0', '61_90' => '0', '90_plus' => '0'];
    }

    private function agingBucket(int $days): string
    {
        return match (true) {
            $days <= 0 => 'current',
            $days <= 30 => '1_30',
            $days <= 60 => '31_60',
            $days <= 90 => '61_90',
            default => '90_plus',
        };
    }

    private function scaleAging(array $buckets): array
    {
        return collect($buckets)
            ->map(fn ($value) => $this->decimal(BigDecimal::of((string) $value)))
            ->all();
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
