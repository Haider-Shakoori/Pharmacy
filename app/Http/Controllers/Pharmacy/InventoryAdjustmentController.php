<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreInventoryAdjustmentRequest;
use App\Models\ProductBatch;
use App\Services\Inventory\InventoryAdjustmentService;
use Illuminate\Http\RedirectResponse;

class InventoryAdjustmentController extends Controller
{
    public function store(
        StoreInventoryAdjustmentRequest $request,
        InventoryAdjustmentService $service,
    ): RedirectResponse {
        $batch = ProductBatch::query()->findOrFail($request->validated('product_batch_id'));

        $adjustment = $service->post(
            $batch,
            (string) $request->validated('quantity_delta'),
            $request->validated('reason_code'),
            $request->validated('reason'),
            $request->user()->id,
        );

        return redirect()
            ->route('pharmacy.inventory.show', $batch)
            ->with('success', "Inventory adjustment {$adjustment->number} posted.");
    }
}
