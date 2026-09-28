<?php

namespace App\Services\Alerts;

use App\Models\Medicine;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\DailyClosing\BusinessDateResolver;
use App\Services\Settings\PharmacySettings;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

class OperationalAlertService
{
    public function __construct(
        private readonly PharmacySettings $settings,
        private readonly BusinessDateResolver $businessDates,
    ) {}

    public function snapshot(Tenant $tenant): array
    {
        return $this->inside($tenant, function () use ($tenant): array {
            $policy = $this->settings->inventory($tenant);
            $timezone = $this->settings->timezone($tenant);
            $today = CarbonImmutable::now($timezone)->startOfDay();
            $nearExpiryEnd = $today->addDays($policy['near_expiry_days']);

            $medicines = Medicine::query()
                ->where('is_active', true)
                ->with(['batches' => function ($query) use ($today): void {
                    $query
                        ->where('status', 'active')
                        ->where('available_quantity', '>', 0)
                        ->where(function ($query) use ($today): void {
                            $query->whereNull('expires_at')
                                ->orWhereDate('expires_at', '>=', $today->toDateString());
                        });
                }])
                ->orderBy('brand_name')
                ->get();

            $lowStock = $medicines
                ->filter(function (Medicine $medicine) use ($policy): bool {
                    $available = $medicine->batches->reduce(
                        fn (BigDecimal $carry, ProductBatch $batch): BigDecimal => $carry->plus((string) $batch->available_quantity),
                        BigDecimal::zero(),
                    );
                    $reorder = BigDecimal::of((string) $medicine->reorder_level);
                    $threshold = $reorder->isGreaterThan(BigDecimal::zero())
                        ? $reorder
                        : BigDecimal::of((string) $policy['low_stock_threshold']);

                    return $available->isLessThanOrEqualTo($threshold);
                })
                ->map(function (Medicine $medicine) use ($policy): array {
                    $available = $medicine->batches->reduce(
                        fn (BigDecimal $carry, ProductBatch $batch): BigDecimal => $carry->plus((string) $batch->available_quantity),
                        BigDecimal::zero(),
                    );
                    $reorder = BigDecimal::of((string) $medicine->reorder_level);
                    $threshold = $reorder->isGreaterThan(BigDecimal::zero())
                        ? (string) $medicine->reorder_level
                        : number_format($policy['low_stock_threshold'], 4, '.', '');

                    return [
                        'medicine_id' => $medicine->id,
                        'medicine_code' => $medicine->medicine_code,
                        'name' => $medicine->brand_name,
                        'available_quantity' => (string) $available,
                        'threshold' => $threshold,
                    ];
                })
                ->values();

            $nearExpiry = ProductBatch::query()
                ->with(['medicine:id,brand_name,medicine_code', 'location:id,name'])
                ->where('status', 'active')
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', $today->toDateString())
                ->whereDate('expires_at', '<=', $nearExpiryEnd->toDateString())
                ->orderBy('expires_at')
                ->get()
                ->map(fn (ProductBatch $batch): array => $this->batchAlert($batch))
                ->values();

            $expired = ProductBatch::query()
                ->with(['medicine:id,brand_name,medicine_code', 'location:id,name'])
                ->where('status', 'active')
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '<', $today->toDateString())
                ->orderBy('expires_at')
                ->get()
                ->map(fn (ProductBatch $batch): array => $this->batchAlert($batch))
                ->values();

            $businessDate = $this->businessDates->resolve($tenant);
            $todaySales = (string) (Sale::query()
                ->where('status', 'completed')
                ->whereDate('business_date', $businessDate)
                ->sum('grand_total') ?? '0');

            return [
                'policy' => $policy,
                'business_date' => $businessDate,
                'today_sales' => $todaySales,
                'low_stock' => $lowStock->all(),
                'near_expiry' => $nearExpiry->all(),
                'expired' => $expired->all(),
                'counts' => [
                    'low_stock' => $lowStock->count(),
                    'near_expiry' => $nearExpiry->count(),
                    'expired' => $expired->count(),
                    'total' => $lowStock->count() + $nearExpiry->count() + $expired->count(),
                ],
            ];
        });
    }

    private function batchAlert(ProductBatch $batch): array
    {
        return [
            'batch_id' => $batch->id,
            'medicine_id' => $batch->medicine_id,
            'medicine_code' => $batch->medicine?->medicine_code,
            'name' => $batch->medicine?->brand_name ?? 'Medicine',
            'batch_number' => $batch->batch_number ?: '—',
            'location' => $batch->location?->name ?? '—',
            'available_quantity' => (string) $batch->available_quantity,
            'expires_at' => $batch->expires_at?->toDateString(),
        ];
    }

    private function inside(Tenant $tenant, callable $callback): mixed
    {
        if (tenancy()->initialized && (string) tenant('id') === (string) $tenant->getTenantKey()) {
            return $callback();
        }

        return $tenant->run($callback);
    }
}
