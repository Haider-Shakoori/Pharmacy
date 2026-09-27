<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreBranchRequest;
use App\Http\Requests\Pharmacy\StoreStockLocationRequest;
use App\Models\Branch;
use App\Models\StockLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryLocationController extends Controller
{
    public function index(): View
    {
        return view('pharmacy.inventory.locations', [
            'branches' => Branch::query()->with('locations')->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function storeBranch(StoreBranchRequest $request): RedirectResponse
    {
        Branch::query()->create([
            ...$request->validated(),
            'is_default' => false,
            'is_active' => true,
        ]);

        return back()->with('success', 'Branch created.');
    }

    public function storeLocation(StoreStockLocationRequest $request): RedirectResponse
    {
        StockLocation::query()->create([
            ...$request->validated(),
            'is_default' => false,
            'is_active' => true,
        ]);

        return back()->with('success', 'Stock location created.');
    }
}
