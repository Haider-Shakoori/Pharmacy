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

            $oldIds = $recipient->notifications()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->skip(self::RETENTION_LIMIT)
                ->pluck('id');

            if ($oldIds->isNotEmpty()) {
                $recipient->notifications()->whereIn('id', $oldIds->all())->delete();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
