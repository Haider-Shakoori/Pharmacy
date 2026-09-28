<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Services\Alerts\OperationalAlertService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;

class AlertController extends Controller
{
    public function __invoke(
        TenantContext $tenantContext,
        OperationalAlertService $alerts,
    ): View {
        $tenant = $tenantContext->tenant();

        return view('pharmacy.alerts.index', [
            'tenant' => $tenant,
            'alerts' => $alerts->snapshot($tenant),
        ]);
    }
}
