<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\DesktopUserSession;
use App\Models\LicenseActivation;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Licensing\LicenseSupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $subscriptions = Subscription::query()
            ->with(['business.tenant', 'plan', 'license'])
            ->withCount([
                'license as active_windows_count' => fn ($query) => $query
                    ->join('license_activations', 'licenses.id', '=', 'license_activations.license_id')
                    ->where('license_activations.platform', 'windows')
                    ->whereNull('license_activations.revoked_at'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('business', function ($query) use ($search): void {
                    $query->where('pharmacy_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('platform.licenses.index', compact('subscriptions', 'search'));
    }

    public function show(Subscription $subscription): View
    {
        $subscription->load([
            'business.tenant',
            'plan',
            'license.activationCodes' => fn ($query) => $query->latest('generated_at'),
            'license.activations' => fn ($query) => $query
                ->with(['desktopSessions' => fn ($query) => $query->latest('last_seen_at')])
                ->latest('last_seen_at'),
            'license.supportActions' => fn ($query) => $query
                ->with('platformAdmin')
                ->latest()
                ->limit(30),
        ]);

        return view('platform.licenses.show', [
            'subscription' => $subscription,
            'license' => $subscription->license,
            'syncEnabled' => (bool) ($subscription->business->desktop_cloud_sync_enabled ?? true),
        ]);
    }

    public function issueWindowsKey(
        Subscription $subscription,
        LicenseSupportService $support,
    ): RedirectResponse {
        $license = $subscription->license;

        if ($license === null) {
            throw ValidationException::withMessages([
                'license' => 'Generate the subscription license before issuing a Windows activation key.',
            ]);
        }

        $plainText = $support->issueWindowsKey($license, $this->platformAdmin());

        return back()
            ->with('success', 'A one-time Windows activation key was issued. It will be consumed by the first successful PC activation.')
            ->with('generated_license_key', $plainText);
    }

    public function replaceWindowsDevice(
        Subscription $subscription,
        LicenseActivation $activation,
        LicenseSupportService $support,
    ): RedirectResponse {
        $this->assertActivationBelongsToSubscription($subscription, $activation);

        if ($activation->platform !== 'windows') {
            throw ValidationException::withMessages([
                'activation' => 'Only Windows activations can use the PC replacement flow.',
            ]);
        }

        $plainText = $support->replaceWindowsDevice($activation, $this->platformAdmin());

        return back()
            ->with('success', 'The previous PC was revoked and a new one-time replacement key was issued.')
            ->with('generated_license_key', $plainText);
    }

    public function revokeDevice(
        Subscription $subscription,
        LicenseActivation $activation,
        LicenseSupportService $support,
    ): RedirectResponse {
        $this->assertActivationBelongsToSubscription($subscription, $activation);

        $support->revokeDevice($activation, $this->platformAdmin());

        return back()->with('success', 'The device activation and its active desktop sessions were revoked.');
    }

    public function forceSignOut(
        Subscription $subscription,
        DesktopUserSession $session,
        LicenseSupportService $support,
    ): RedirectResponse {
        $session->loadMissing('activation');

        $this->assertActivationBelongsToSubscription($subscription, $session->activation);

        $support->forceSignOut($session, $this->platformAdmin());

        return back()->with('success', 'The desktop user session was revoked. It will be rejected on the next online request.');
    }

    public function regenerate(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $plainText = $keys->rotate($subscription);

        return back()
            ->with('success', 'The subscription license was rotated. All existing device activations and unused one-time keys were revoked.')
            ->with('generated_license_key', $plainText);
    }

    public function revoke(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $keys->revoke($subscription);

        return back()->with('success', 'License and active device activations revoked.');
    }

    private function platformAdmin(): ?PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        return $admin instanceof PlatformAdmin ? $admin : null;
    }

    private function assertActivationBelongsToSubscription(
        Subscription $subscription,
        LicenseActivation $activation,
    ): void {
        $licenseId = $subscription->license?->id;

        if ($licenseId === null || (string) $activation->license_id !== (string) $licenseId) {
            abort(404);
        }
    }
}
