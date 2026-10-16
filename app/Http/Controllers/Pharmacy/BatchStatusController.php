<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\ChangeBatchStatusRequest;
use App\Models\ProductBatch;
use App\Notifications\InventoryActivityNotification;
use App\Services\Alerts\InventoryActivityNotifier;
use App\Services\Inventory\BatchStatusService;
use Illuminate\Http\RedirectResponse;

class BatchStatusController extends Controller
{
    public function __invoke(
        ChangeBatchStatusRequest $request,
        ProductBatch $productBatch,
        BatchStatusService $service,
        InventoryActivityNotifier $notifier,
    ): RedirectResponse {
        $previousStatus = $productBatch->status;
        $updatedBatch = $service->change(
            $productBatch,
            $request->validated('status'),
            $request->validated('reason'),
            $request->user()->id,
        );

        if ($previousStatus !== $updatedBatch->status) {
            $notifier->record($request->user(), new InventoryActivityNotification(
                    'Batch status changed',
                    'Batch '.($updatedBatch->batch_number ?: $updatedBatch->id).' changed from '.$previousStatus.' to '.$updatedBatch->status.'.',
                    (string) $updatedBatch->id,
                    in_array($updatedBatch->status, ['recalled', 'quarantined', 'damaged'], true) ? 'warning' : 'info',
            ));
        }

        return back()->with('success', 'Batch status updated with an audit event.');
    }
}
