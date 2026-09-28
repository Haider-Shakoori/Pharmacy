<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Http\RedirectResponse;

class TenantTrialController extends Controller
{
    public function __invoke(Tenant $tenant, TrialProvisioner $trials): RedirectResponse
    {
        $licenseKey = $trials->provision($tenant);

        return back()
            ->with('success', 'Seven-day hosted trial started. Tenant access and Android licensing are active for the trial period.')
            ->with('generated_license_key', $licenseKey);
    }
}
