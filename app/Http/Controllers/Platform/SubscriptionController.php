<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $subscriptions = Subscription::query()
            ->with(['tenant', 'plan'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('tenant', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('platform.subscriptions.index', compact('subscriptions', 'search', 'status'));
    }

    public function edit(Tenant $tenant): View
    {
        $tenant->load('subscription.plan');

        return view('platform.subscriptions.edit', [
            'tenant' => $tenant,
            'subscription' => $tenant->subscription,
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($tenant, $validated): void {
            $subscription = Subscription::query()->firstOrNew([
                'tenant_id' => $tenant->id,
            ]);

            $subscription->fill($validated);
            $subscription->tenant_id = $tenant->id;
            $subscription->save();
        });

        return redirect()
            ->route('platform.subscriptions.edit', $tenant)
            ->with('success', 'Subscription assignment updated.');
    }
}
