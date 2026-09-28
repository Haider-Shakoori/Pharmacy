<?php

namespace App\Http\Controllers\Offline;

use App\Http\Controllers\Controller;
use App\Services\Offline\OfflineInstallationIdentity;
use App\Services\Offline\OfflineLicenseActivator;
use App\Services\Offline\OfflineLicenseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfflineLicenseController extends Controller
{
    public function show(
        OfflineLicenseManager $licenses,
        OfflineInstallationIdentity $identity,
    ): View {
        return view('offline.license', [
            'status' => $licenses->status(),
            'installationId' => $identity->installationId(),
            'fingerprint' => $identity->machineFingerprintHash(),
        ]);
    }

    public function activate(
        Request $request,
        OfflineLicenseActivator $activator,
    ): RedirectResponse {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:120'],
        ]);

        $payload = $activator->activate($validated['license_key']);

        return redirect()
            ->route('offline.license.show')
            ->with('success', 'BusinessOS Pharmacy was activated until '.date('Y-m-d H:i', (int) $payload['expires_at']).'.');
    }
}
