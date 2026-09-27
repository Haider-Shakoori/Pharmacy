<?php

namespace App\Services\Inventory;

use App\Models\BatchStatusEvent;
use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchStatusService
{
    private const ALLOWED = ['active', 'quarantined', 'recalled', 'damaged'];

    public function change(ProductBatch $batch, string $status, string $reason, ?int $actorId): ProductBatch
    {
        if (! in_array($status, self::ALLOWED, true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported batch status.']);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for a batch status change.']);
        }

        return DB::transaction(function () use ($batch, $status, $reason, $actorId): ProductBatch {
            $locked = ProductBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($locked->status === $status) {
                return $locked;
            }

            $from = $locked->status;
            $locked->update(['status' => $status]);

            BatchStatusEvent::query()->create([
                'product_batch_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $status,
                'reason' => $reason,
                'actor_id' => $actorId,
                'changed_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
