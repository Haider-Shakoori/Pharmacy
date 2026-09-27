<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Subscriptions\SubscriptionHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(
        Request $request,
        SubscriptionHealthService $health,
    ): View {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $subscriptions = Subscription::query()
            ->with(['business.tenant', 'plan', 'license'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('business', function ($query) use ($search): void {
                    $query->where('pharmacy_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        $subscriptions->getCollection()->each(function (Subscription $subscription) use ($health): void {
            $subscription->setAttribute('health_state', $health->forSubscription($subscription));
        });

        return view('platform.subscriptions.index', compact('subscriptions', 'search', 'status'));
    }

    public function edit(Tenant $tenant): View
    {
        $tenant->load('business.subscription.plan', 'business.subscription.license.activations');

        return view('platform.subscriptions.edit', [
            'tenant' => $tenant,
            'subscription' => $tenant->business?->subscription,
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(
        UpdateSubscriptionRequest $request,
        Tenant $tenant,
        LicenseKeyService $keys,
    ): RedirectResponse {
        $validated = $request->validated();
        $generatedKey = null;

        DB::transaction(function () use ($tenant, $validated, $keys, &$generatedKey): void {
            $subscription = Subscription::query()->firstOrNew([
                'business_id' => $tenant->business()->firstOrFail()->id,
            ]);

            $subscription->fill($validated);

            if ($subscription->status->value !== 'trial') {
                $subscription->trial_started_at = null;
                $subscription->trial_ends_at = null;
            }

            $subscription->business_id = $tenant->business()->firstOrFail()->id;
            $subscription->save();

            $generatedKey = $keys->ensureForSubscription($subscription);
        });

        $response = redirect()
            ->route('platform.subscriptions.edit', $tenant)
            ->with('success', 'Subscription assignment updated.');

        if ($generatedKey !== null) {
            $response->with('generated_license_key', $generatedKey);
        }

        return $response;
    }
}
