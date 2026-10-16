<?php

namespace App\Services\Alerts;

use App\Models\User;
use App\Notifications\InventoryActivityNotification;
use Throwable;

class InventoryActivityNotifier
{
    public const RETENTION_LIMIT = 1000;

    /**
     * A notification failure must never make an already committed stock action
     * appear unsuccessful or invite a second attempt.
     */
    public function record(User $recipient, InventoryActivityNotification $notification): void
    {
        try {
            $recipient->notify($notification);

            // A bounded LIMIT is required with OFFSET on SQLite and avoids
            // accumulating large id lists for tenants with long histories.
            do {
                $oldIds = $recipient->notifications()
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(250)
                    ->offset(self::RETENTION_LIMIT)
                    ->pluck('id');

                if ($oldIds->isNotEmpty()) {
                    $recipient->notifications()->whereIn('id', $oldIds->all())->delete();
                }
            } while ($oldIds->isNotEmpty());
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
