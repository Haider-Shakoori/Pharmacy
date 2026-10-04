<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\LicenseActivation;
use App\Models\LicenseSupportAction;
use App\Models\Subscription;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'license' => fn ($query) => $query->withCount('activations'),
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

        $license = $subscription->license;
        $supportActions = $license === null
            ? collect()
            : LicenseSupportAction::query()
                ->where('license_id', $license->id)
                ->latest('created_at')
                ->limit(50)
                ->get();

        return view('platform.licenses.show', compact(
            'subscription',
            'license',
            'supportActions',
        ));
    }

    public function forceSignOut(
        Request $request,
        Subscription $subscription,
        LicenseActivation $activation,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $license = $subscription->license()->firstOrFail();
        abort_unless((string) $activation->license_id === (string) $license->id, 404);

        DB::transaction(function () use ($request, $license, $activation, $validated): void {
            $locked = LicenseActivation::query()
                ->lockForUpdate()
                ->findOrFail($activation->id);

            $snapshot = $this->activationSnapshot($locked);

            $locked->forceFill([
                'session_version' => (int) $locked->session_version + 1,
                'current_user_id' => null,
                'current_user_name' => null,
                'current_user_email' => null,
                'session_expires_at' => null,
                'session_signed_out_at' => now(),
            ])->save();

            LicenseSupportAction::query()->create([
                'license_id' => $license->id,
                'activation_id' => $locked->id,
                'platform_admin_id' => $request->user('platform')?->id,
                'action' => 'force_sign_out',
                'reason' => $validated['reason'],
                'metadata' => $snapshot,
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'The desktop session was force-signed out.');
    }

    public function resetDevice(
        Request $request,
        Subscription $subscription,
        LicenseActivation $activation,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $license = $subscription->license()->firstOrFail();
        abort_unless((string) $activation->license_id === (string) $license->id, 404);

        $plainText = DB::transaction(function () use (
            $request,
            $subscription,
            $license,
            $activation,
            $keys,
            $validated,
        ): string {
            $locked = LicenseActivation::query()
                ->lockForUpdate()
                ->findOrFail($activation->id);

            $snapshot = $this->activationSnapshot($locked);
            $plainText = $keys->rotate($subscription);

            LicenseSupportAction::query()->create([
                'license_id' => $license->id,
                'activation_id' => $locked->id,
                'platform_admin_id' => $request->user('platform')?->id,
                'action' => 'reset_and_reassign',
                'reason' => $validated['reason'],
                'metadata' => $snapshot,
                'created_at' => now(),
            ]);

            return $plainText;
        });

        return back()
            ->with('success', 'The previous desktop activation was revoked. A new one-time key was issued for support reactivation.')
            ->with('generated_license_key', $plainText);
    }

    public function regenerate(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $plainText = $keys->rotate($subscription);

        LicenseSupportAction::query()->create([
            'license_id' => $subscription->license()->value('id'),
            'activation_id' => null,
            'platform_admin_id' => request()->user('platform')?->id,
            'action' => 'regenerate_key',
            'reason' => 'Manual key regeneration from the platform license list.',
            'metadata' => null,
            'created_at' => now(),
        ]);

        return back()
            ->with('success', 'A new one-time license key was generated. Previous device activations were revoked.')
            ->with('generated_license_key', $plainText);
    }

    public function revoke(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $licenseId = $subscription->license()->value('id');
        $keys->revoke($subscription);

        if ($licenseId !== null) {
            LicenseSupportAction::query()->create([
                'license_id' => $licenseId,
                'activation_id' => null,
                'platform_admin_id' => request()->user('platform')?->id,
                'action' => 'revoke_license',
                'reason' => 'License revoked from the platform license list.',
                'metadata' => null,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'License and active device activations revoked.');
    }

    private function activationSnapshot(LicenseActivation $activation): array
    {
        return [
            'device_id' => $activation->device_id,
            'device_name' => $activation->device_name,
            'platform' => $activation->platform,
            'app_version' => $activation->app_version,
            'device_model' => $activation->device_model,
            'os_version' => $activation->os_version,
            'build_number' => $activation->build_number,
            'current_user_id' => $activation->current_user_id,
            'current_user_name' => $activation->current_user_name,
            'current_user_email' => $activation->current_user_email,
            'last_ip_address' => $activation->last_ip_address,
            'activated_at' => $activation->activated_at?->toIso8601String(),
            'last_seen_at' => $activation->last_seen_at?->toIso8601String(),
        ];
    }
}
