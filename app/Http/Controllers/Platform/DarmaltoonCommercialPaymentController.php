<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Subscriptions\DarmaltoonCommercialBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DarmaltoonCommercialPaymentController extends Controller
{
    public function initial(Request $request, Tenant $tenant, DarmaltoonCommercialBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'min:3', 'max:150'],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'payment_verified' => ['accepted'],
        ]);

        $result = $billing->recordInitial($tenant, $request->user('platform'),
            trim($data['payment_reference']), (string) $data['amount']);

        $response = redirect()->route('platform.subscriptions.edit', $tenant)
            ->with('success', 'Verified first-year payment recorded and subscription activated. Founding tier: '.
                $result['enrollment']->offer_tier.'.');

        if ($result['new_key'] !== null) {
            $response->with('generated_license_key', $result['new_key']);
        }

        return $response;
    }

    public function renew(Request $request, Tenant $tenant, DarmaltoonCommercialBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'min:3', 'max:150'],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'payment_verified' => ['accepted'],
        ]);

        $billing->recordRenewal($tenant, $request->user('platform'),
            trim($data['payment_reference']), (string) $data['amount']);

        return redirect()->route('platform.subscriptions.edit', $tenant)
            ->with('success', 'Verified annual renewal payment recorded; subscription expiry extended by 1 year.');
    }
}
