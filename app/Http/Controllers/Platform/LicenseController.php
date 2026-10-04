<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\LicenseActivation;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Licensing\LicenseSupportService;
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
                'license.activations' => fn ($query) => $query
                    ->where('platform', 'windows')
                    ->latest('last_seen_at'),
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
                ->latest('activated_at'),
            'license.supportActions.platformAdmin',
        ]);

        return view('platform.licenses.show', compact('subscription'));
    }

    public function regenerate(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $plainText = $keys->rotate($subscription);

        return back()
            ->with('success', 'A new master license key was generated. Previous device activations were revoked.')
            ->with('generated_license_key', $plainText);
    }

    public function issueNextKey(
        Request $request,
        Subscription $subscription,
        LicenseSupportService $support,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $plainText = $support->issueNextKey(
            $subscription,
            $this->platformAdmin($request),
            $validated['reason'] ?? null,
        );

        return back()
            ->with('success', 'A new one-time Windows activation key was issued. It can be used successfully only once.')
            ->with('generated_license_key', $plainText);
    }

    public function forceSignOut(
        Request $request,
        LicenseActivation $activation,
        LicenseSupportService $support,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $support->forceSignOut(
            $activation,
            $this->platformAdmin($request),
            $validated['reason'] ?? null,
        );

        return back()->with('success', 'The desktop user session was invalidated. The activation remains assigned to this PC.');
    }

    public function releaseAndReassign(
        Request $request,
        LicenseActivation $activation,
        LicenseSupportService $support,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $plainText = $support->releaseAndReassign(
            $activation,
            $this->platformAdmin($request),
            $validated['reason'],
        );

        return back()
            ->with('success', 'The old PC activation was revoked and a new one-time replacement key was issued.')
            ->with('generated_license_key', $plainText);
    }

    public function revoke(
        Subscription $subscription,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $keys->revoke($subscription);

        return back()->with('success', 'License and active device activations revoked.');
    }

    private function platformAdmin(Request $request): PlatformAdmin
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->user('platform');

        return $admin;
    }
}
