<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\UpdateSettingsRequest;
use App\Services\Settings\PharmacySettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(TenantContext $tenantContext, PharmacySettings $settings): View
    {
        $tenant = $tenantContext->tenant();

        return view('pharmacy.settings.edit', [
            'tenant' => $tenant,
            'profile' => $settings->profile($tenant),
            'closing' => $settings->dailyClosing($tenant),
        ]);
    }

    public function update(
        UpdateSettingsRequest $request,
        TenantContext $tenantContext,
        PharmacySettings $settings,
    ): RedirectResponse {
        $validated = $request->validated();
        $tenant = $tenantContext->tenant();

        $settings->persist(
            $tenant,
            [
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'receipt_footer' => $validated['receipt_footer'],
                'timezone' => $validated['timezone'],
                'locale' => $validated['locale'],
            ],
            [
                'business_day_rollover_time' => $validated['business_day_rollover_time'],
                'opening_cash_mode' => $validated['opening_cash_mode'],
                'require_counted_cash' => $validated['require_counted_cash'],
                'variance_note_threshold' => (float) $validated['variance_note_threshold'],
                'allow_reopen' => $validated['allow_reopen'],
                'require_close_before_next_day' => $validated['require_close_before_next_day'],
                'block_online_sales_after_close' => $validated['block_online_sales_after_close'],
                'warn_unsynced_devices_before_close' => $validated['warn_unsynced_devices_before_close'],
            ],
        );

        $request->session()->put('locale', $validated['locale']);

        return back()->with('success', __('settings.saved'));
    }
}
