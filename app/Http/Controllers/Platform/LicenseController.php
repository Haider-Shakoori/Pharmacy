<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\LicenseActivation;
use App\Models\LicenseSupportAction;
use App\Models\Subscription;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $subscriptions = Subscription::query()
            ->with([
                'business.tenant',
                'plan',
                'license.activations' => fn ($query) => $query->latest('last_seen_at'),
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
            'license.activations' => fn ($query) => $query
                ->latest('last_seen_at')
                ->latest('activated_at'),
        ]);

        $auditActions = $subscription->license
            ? $subscription->license->supportActions()
                ->with(['platformAdmin', 'activation'])
                ->latest()
                ->limit(50)
                ->get()
            : collect();

        return view('platform.licenses.show', compact(
            'subscription',
            'auditActions',
        ));
    }

    public function reassignWindows(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $license = $subscription->license()->firstOrFail();
        $activeWindows = $license->activations()
            ->where('platform', 'windows')
            ->whereNull('revoked_at')
            ->get(['id', 'device_id', 'device_name']);

        $plainText = $keys->reissueWindowsActivationKey($subscription);

        LicenseSupportAction::query()->create([
            'license_id' => $license->id,
            'platform_admin_id' => auth('platform')->id(),
            'action' => 'windows_reassigned',
            'metadata' => [
                'revoked_windows_devices' => $activeWindows
                    ->map(fn (LicenseActivation $activation): array => [
                        'activation_id' => $activation->id,
                        'device_id' => $activation->device_id,
                        'device_name' => $activation->device_name,
                    ])
                    ->values()
                    ->all(),
                'new_windows_key_version' => $license->fresh()->windows_activation_key_version,
            ],
        ]);

        return back()
            ->with('success', 'The previous Windows activation was revoked. Give the customer the new one-time Windows activation key below.')
            ->with('generated_windows_activation_key', $plainText);
    }

    public function forceSignOut(
        Subscription $subscription,
        LicenseActivation $activation,
    ): RedirectResponse {
        $activation = $this->activationForSubscription(
            $subscription,
            $activation,
        );

        $previousUser = [
            'user_id' => $activation->current_user_id,
            'name' => $activation->current_user_name,
            'email' => $activation->current_user_email,
        ];

        $activation->forceFill([
            'session_version' => max(1, (int) $activation->session_version + 1),
            'current_user_id' => null,
            'current_user_name' => null,
            'current_user_email' => null,
            'session_issued_at' => null,
            'session_expires_at' => null,
            'last_session_activity_at' => null,
        ])->save();

        LicenseSupportAction::query()->create([
            'license_id' => $activation->license_id,
            'license_activation_id' => $activation->id,
            'platform_admin_id' => auth('platform')->id(),
            'action' => 'force_sign_out',
            'metadata' => [
                'device_id' => $activation->device_id,
                'device_name' => $activation->device_name,
                'previous_user' => $previousUser,
            ],
        ]);

        return back()->with(
            'success',
            'The current desktop session was invalidated. The user must sign in again.',
        );
    }

    public function revokeActivation(
        Subscription $subscription,
        LicenseActivation $activation,
    ): RedirectResponse {
        $activation = $this->activationForSubscription(
            $subscription,
            $activation,
        );

        $activation->forceFill([
            'revoked_at' => now(),
            'session_version' => max(1, (int) $activation->session_version + 1),
            'current_user_id' => null,
            'current_user_name' => null,
            'current_user_email' => null,
            'session_issued_at' => null,
            'session_expires_at' => null,
            'last_session_activity_at' => null,
        ])->save();

        LicenseSupportAction::query()->create([
            'license_id' => $activation->license_id,
            'license_activation_id' => $activation->id,
            'platform_admin_id' => auth('platform')->id(),
            'action' => 'device_revoked',
            'metadata' => [
                'device_id' => $activation->device_id,
                'device_name' => $activation->device_name,
                'platform' => $activation->platform,
            ],
        ]);

        return back()->with(
            'success',
            'The device activation was revoked. A Windows device cannot be activated again until support reassigns the Windows license.',
        );
    }

    private function activationForSubscription(
        Subscription $subscription,
        LicenseActivation $activation,
    ): LicenseActivation {
        $license = $subscription->license;

        abort_if(
            $license === null
            || (string) $activation->license_id !== (string) $license->id,
            404,
        );

        return $activation;
    }

    public function regenerate(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $plainText = $keys->rotate($subscription);

        return back()
            ->with('success', 'A new license key was generated. Previous device activations were revoked.')
            ->with('generated_license_key', $plainText);
    }

    public function revoke(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $keys->revoke($subscription);

        return back()->with('success', 'License and active device activations revoked.');
    }
}
