<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class InventoryActivityNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $reference,
        private readonly string $severity = 'info',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => 'inventory',
            'title' => $this->title,
            'message' => $this->message,
            'reference' => $this->reference,
            'severity' => $this->severity,
        ];
    }
}
