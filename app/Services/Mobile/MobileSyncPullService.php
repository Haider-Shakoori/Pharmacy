<?php

namespace App\Services\Mobile;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Permission;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockLocation;
use App\Models\User;
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
                'branches' => Branch::query()->select([
                    'id',
                    'code',
                    'name',
                    'address',
                    'is_default',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]),
                'stock_locations' => StockLocation::query()->select([
                    'id',
                    'branch_id',
                    'code',
                    'name',
                    'kind',
                    'is_default',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]),
                'medicines' => Medicine::query()->select([
                    'id',
                    'medicine_category_id',
                    'manufacturer_id',
                    'medicine_code',
                    'barcode',
                    'brand_name',
                    'generic_name',
                    'strength',
                    'dosage_form',
                    'purchase_unit',
                    'sale_unit',
                    'units_per_purchase_unit',
                    'reorder_level',
                    'prescription_required',
                    'batch_tracking_required',
                    'expiry_tracking_required',
                    'is_active',
                    'notes',
                    'created_at',
                    'updated_at',
                ]),
                'inventory' => ProductBatch::query()->select([
                    'id',
                    'medicine_id',
                    'supplier_id',
                    'purchase_order_id',
                    'goods_receipt_id',
                    'branch_id',
                    'stock_location_id',
                    'batch_number',
                    'batch_key',
                    'manufactured_at',
                    'expires_at',
                    'available_quantity',
                    'received_quantity',
                    'sale_price',
                    'purchase_cost',
                    'status',
                    'last_movement_at',
                    'created_at',
                    'updated_at',
                ]),
                'customers' => Customer::query()->select([
                    'id',
                    'name',
                    'phone',
                    'email',
                    'credit_limit',
                    'is_active',
                    'notes',
                    'created_at',
                    'updated_at',
                ]),
                'permissions' => Permission::query()->select([
                    'id',
                    'code',
                    'name',
                    'description',
                    'created_at',
                    'updated_at',
                ]),
                'roles' => Role::query()
                    ->with('permissions:id,code,name,description')
                    ->withCount('users')
                    ->select([
                        'id',
                        'name',
                        'code',
                        'is_system',
                        'created_at',
                        'updated_at',
                    ]),
                'users' => User::query()
                    ->with([
                        'roles:id,name,code',
                        'roles.permissions:id,code,name,description',
                    ])
                    ->select([
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'created_at',
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
                'branches' => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'address' => $row->address,
                    'is_default' => (bool) $row->is_default,
                    'is_active' => (bool) $row->is_active,
                    'is_deleted' => false,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'stock_locations' => [
                    'id' => $row->id,
                    'branch_id' => $row->branch_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'kind' => $row->kind,
                    'is_default' => (bool) $row->is_default,
                    'is_active' => (bool) $row->is_active,
                    'is_deleted' => false,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'medicines' => [
                    'id' => $row->id,
                    'medicine_category_id' => $row->medicine_category_id,
                    'manufacturer_id' => $row->manufacturer_id,
                    'medicine_code' => $row->medicine_code,
                    'barcode' => $row->barcode,
                    'brand_name' => $row->brand_name,
                    'generic_name' => $row->generic_name,
                    'strength' => $row->strength,
                    'dosage_form' => $row->dosage_form,
                    'purchase_unit' => $row->purchase_unit,
                    'sale_unit' => $row->sale_unit,
                    'units_per_purchase_unit' => (string) $row->units_per_purchase_unit,
                    'reorder_level' => (string) $row->reorder_level,
                    'prescription_required' => (bool) $row->prescription_required,
                    'batch_tracking_required' => (bool) $row->batch_tracking_required,
                    'expiry_tracking_required' => (bool) $row->expiry_tracking_required,
                    'is_active' => (bool) $row->is_active,
                    'notes' => $row->notes,
                    'is_deleted' => false,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'inventory' => [
                    'id' => $row->id,
                    'medicine_id' => $row->medicine_id,
                    'supplier_id' => $row->supplier_id,
                    'purchase_order_id' => $row->purchase_order_id,
                    'goods_receipt_id' => $row->goods_receipt_id,
                    'branch_id' => $row->branch_id,
                    'stock_location_id' => $row->stock_location_id,
                    'batch_number' => $row->batch_number ?? '',
                    'batch_key' => $row->batch_key,
                    'manufactured_at' => $row->manufactured_at?->toDateString(),
                    'expires_at' => $row->expires_at?->toDateString(),
                    'available_quantity' => (string) $row->available_quantity,
                    'received_quantity' => (string) $row->received_quantity,
                    'sale_price' => $row->sale_price === null
                        ? null
                        : (string) $row->sale_price,
                    'purchase_cost' => (string) $row->purchase_cost,
                    'status' => $row->status,
                    'last_movement_at' => $row->last_movement_at?->toISOString(),
                    'is_deleted' => false,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'customers' => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'phone' => $row->phone,
                    'email' => $row->email,
                    'credit_limit' => (string) $row->credit_limit,
                    'is_active' => (bool) $row->is_active,
                    'notes' => $row->notes,
                    'is_deleted' => ! (bool) $row->is_active,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'permissions' => [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'description' => $row->description,
                    'is_deleted' => false,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'roles' => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'code' => $row->code,
                    'is_system' => (bool) $row->is_system,
                    'users_count' => (int) $row->users_count,
                    'permissions' => $row->permissions
                        ->map(fn (Permission $permission): array => [
                            'id' => (int) $permission->id,
                            'code' => $permission->code,
                            'name' => $permission->name,
                            'description' => $permission->description,
                        ])->values()->all(),
                    'is_deleted' => false,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
                'users' => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'is_active' => (bool) $row->is_active,
                    'roles' => $row->roles
                        ->map(fn (Role $role): array => [
                            'id' => (int) $role->id,
                            'name' => $role->name,
                            'code' => $role->code,
                        ])->values()->all(),
                    'permissions' => $row->roles
                        ->flatMap(fn (Role $role) => $role->permissions)
                        ->unique('id')
                        ->sortBy('code')
                        ->map(fn (Permission $permission): array => [
                            'id' => (int) $permission->id,
                            'code' => $permission->code,
                            'name' => $permission->name,
                            'description' => $permission->description,
                        ])->values()->all(),
                    'is_deleted' => false,
                    'server_created_at' => $row->created_at->toISOString(),
                    'server_updated_at' => $row->updated_at->toISOString(),
                ],
            })
            ->values()
            ->all();
    }
}
