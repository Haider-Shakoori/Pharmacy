<?php

namespace App\Services\Tenancy;

use App\Models\ProvisioningEvent;
use App\Models\Tenant;

class ProvisioningEventRecorder
{
    public function record(
        Tenant $tenant,
        string $step,
        string $status,
        ?string $message = null,
        array $context = [],
    ): void {
        ProvisioningEvent::query()->create([
            'tenant_id' => $tenant->id,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'context' => $context === [] ? null : $context,
            'occurred_at' => now(),
        ]);
    }
}