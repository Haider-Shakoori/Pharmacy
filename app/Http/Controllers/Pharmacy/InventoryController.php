<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Models\StockLocation;
use App\Services\Settings\PharmacySettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(
        Request $request,
        PharmacySettings $settings,
        TenantContext $tenantContext,
    ): View {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $expiry = (string) $request->query('expiry');
        $location = (string) $request->query('location');
        $policy = $settings->inventory($tenantContext->tenant());
        $nearExpiryDate = today()->addDays((int) $policy['near_expiry_days']);

        $batches = ProductBatch::query()
            ->with(['medicine:id,brand_name,generic_name,strength,reorder_level', 'location.branch'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('batch_number', 'like', "%{$search}%")
                ->orWhereHas('medicine', fn ($medicine) => $medicine
                    ->where('brand_name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('medicine_code', 'like', "%{$search}%"))))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($location !== '', fn ($query) => $query->where('stock_location_id', $location))
            ->when($expiry === 'expired', fn ($query) => $query->whereDate('expires_at', '<', today()))
            ->when($expiry === 'near', fn ($query) => $query
                ->whereDate('expires_at', '>=', today())
                ->whereDate('expires_at', '<=', $nearExpiryDate))
            ->when($expiry === 'no_expiry', fn ($query) => $query->whereNull('expires_at'))
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('pharmacy.inventory.index', [
            'batches' => $batches,
            'locations' => StockLocation::query()->with('branch')->where('is_active', true)->orderBy('name')->get(),
            'search' => $search,
            'status' => $status,
            'expiry' => $expiry,
            'location' => $location,
            'policy' => $policy,
        ]);
    }

    public function show(ProductBatch $productBatch): View
    {
        return view('pharmacy.inventory.show', [
            'batch' => $productBatch->load([
                'medicine',
                'supplier',
                'location.branch',
                'movements' => fn ($query) => $query->latest('occurred_at')->limit(100),
                'statusEvents' => fn ($query) => $query->latest('changed_at')->limit(50),
            ]),
        ]);
    }
}
