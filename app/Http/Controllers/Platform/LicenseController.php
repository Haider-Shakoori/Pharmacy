<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
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
            ->with(['tenant', 'plan', 'license'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('tenant', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('platform.licenses.index', compact('subscriptions', 'search'));
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
