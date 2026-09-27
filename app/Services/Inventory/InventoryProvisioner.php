<?php

namespace App\Services\Inventory;

use App\Models\Branch;
use App\Models\StockLocation;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class InventoryProvisioner
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function ensureDefaults(Tenant $tenant): StockLocation
    {
        return $this->tenantContext->run($tenant, function (): StockLocation {
            return DB::transaction(function (): StockLocation {
                $branch = Branch::query()->firstOrCreate(
                    ['code' => 'MAIN'],
                    [
                        'name' => 'Main Branch',
                        'is_default' => true,
                        'is_active' => true,
                    ],
                );

                return StockLocation::query()->firstOrCreate(
                    ['branch_id' => $branch->id, 'code' => 'MAIN'],
                    [
                        'name' => 'Main Stock',
                        'kind' => 'store',
                        'is_default' => true,
                        'is_active' => true,
                    ],
                );
            });
        });
    }

    public function defaultLocation(): StockLocation
    {
        return StockLocation::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->with('branch')
            ->firstOrFail();
    }
}
