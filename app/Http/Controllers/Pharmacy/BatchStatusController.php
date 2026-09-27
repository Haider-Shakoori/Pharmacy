<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\ChangeBatchStatusRequest;
use App\Models\ProductBatch;
use App\Services\Inventory\BatchStatusService;
use Illuminate\Http\RedirectResponse;

class BatchStatusController extends Controller
{
    public function __invoke(
        ChangeBatchStatusRequest $request,
        ProductBatch $productBatch,
        BatchStatusService $service,
    ): RedirectResponse {
        $service->change(
            $productBatch,
            $request->validated('status'),
            $request->validated('reason'),
            $request->user()->id,
        );

        return back()->with('success', 'Batch status updated with an audit event.');
    }
}
