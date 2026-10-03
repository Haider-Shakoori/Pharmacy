<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\Medicine;
use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MobileSyncPullService
{
    public function __construct(
        private readonly MobileSyncCursor $cursors,
    ) {}

    public function pull(
        MobileAccessContext $context,
        string $stream,
        ?string $cursor,
        int $limit,
    ): array {
        $decoded = $this->cursors->decode($cursor);
        $maxPageSize = (int) config('pharmacy.performance.sync_max_page_size', 250);
        $limit = max(1, min($limit, $maxPageSize));

        return $context->tenant->run(function () use (
            $stream,
            $cursor,
            $decoded,
            $limit,
        ): array {
            $query = match ($stream) {
                'medicines' => Medicine::query()->select([
                    'id',
                    'medicine_code',
                    'brand_name',
                    'generic_name',
                    'sale_unit',
                    'is_active',
                    'updated_at',
                ]),
                'inventory' => ProductBatch::query()->select([
                    'id',
                    'medicine_id',
                    'stock_location_id',
                    'batch_number',
                    'expires_at',
                    'available_quantity',
                    'sale_price',
                    'purchase_cost',
                    'status',
                    'created_at',
                    'updated_at',
                ]),
                'customers' => Customer::query()->select([
                    'id',
                    'name',
                    'phone',
                    'is_active',
                    'updated_at',
                ]),
                default => throw ValidationException::withMessages([
                    'stream' => 'The synchronization stream is not supported.',
                ]),
            };

            $this->afterCursor(
                $query,
                $decoded['updated_at'],
                $decoded['id'],
            );

            $rows = $query
                ->orderBy('updated_at')
                ->orderBy('id')
                ->limit($limit + 1)
                ->get();

            $hasMore = $rows->count() > $limit;
            $page = $rows->take($limit)->values();
            $last = $page->last();

            return [
                'stream' => $stream,
                'data' => $this->map($stream, $page),
                'next_cursor' => $last === null
                    ? $cursor
                    : $this->cursors->encode($last),
                'has_more' => $hasMore,
            ];
        });
    }

    private function afterCursor(
        Builder $query,
        object $updatedAt,
        string $id,
    ): void {
        $query->where(function (Builder $query) use ($updatedAt, $id): void {
            $query
                ->where('updated_at', '>', $updatedAt)
                ->orWhere(function (Builder $query) use ($updatedAt, $id): void {
                    $query
                        ->where('updated_at', '=', $updatedAt)
                        ->where('id', '>', $id);
                });
        });
    }

    private function map(string $stream, Collection $rows): array
    {
        return $rows
            ->map(fn ($row): array => match ($stream) {
                'medicines' => [
                    'id' => $row->id,
                    'medicine_code' => $row->medicine_code,
                    'brand_name' => $row->brand_name,
                    'generic_name' => $row->generic_name,
                    'sale_unit' => $row->sale_unit,
                    'is_active' => (bool) $row->is_active,
                    'is_deleted' => false,
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'inventory' => [
                    'id' => $row->id,
                    'medicine_id' => $row->medicine_id,
                    'stock_location_id' => $row->stock_location_id,
                    'batch_number' => $row->batch_number ?? '',
                    'expires_at' => $row->expires_at?->toDateString(),
                    'available_quantity' => (string) $row->available_quantity,
                    'sale_price' => (string) ($row->sale_price ?? '0'),
                    'purchase_cost' => (string) $row->purchase_cost,
                    'status' => $row->status,
                    'is_deleted' => false,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'customers' => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'phone' => $row->phone,
                    'balance' => '0.0000',
                    'is_deleted' => ! (bool) $row->is_active,
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
            })
            ->values()
            ->all();
    }
}
