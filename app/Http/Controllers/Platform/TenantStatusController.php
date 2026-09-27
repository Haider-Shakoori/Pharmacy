<?php

namespace App\Http\Controllers\Platform;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class TenantStatusController extends Controller
{
    public function __invoke(Tenant $tenant, string $status): RedirectResponse
    {
        $tenantStatus = TenantStatus::tryFrom($status);

        abort_if($tenantStatus === null, 404);

        $tenant->update(['status' => $tenantStatus]);

        return back()->with('success', "Pharmacy status changed to {$tenantStatus->value}.");
    }
}
