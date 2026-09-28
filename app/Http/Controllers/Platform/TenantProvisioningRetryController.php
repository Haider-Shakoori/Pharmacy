<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;

class TenantProvisioningRetryController extends Controller
{
    public function __invoke(Tenant $tenant, TenantProvisioningService $provisioner): RedirectResponse
    {
        [$tenant, $licenseKey] = $provisioner->resume($tenant);

        $response = back()->with(
            'success',
            $tenant->provisioning_status === 'application_ready'
                ? 'Provisioning completed and the pharmacy application is ready.'
                : 'Provisioning resumed; the pharmacy is still waiting for domain/TLS readiness.',
        );

        if ($licenseKey !== null) {
            $response->with('generated_license_key', $licenseKey);
        }

        return $response;
    }
}
