<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlanRequest;
use App\Http\Requests\Platform\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $plans = Plan::query()
            ->withCount('subscriptions')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('platform.plans.index', compact('plans', 'search'));
    }

    public function create(): View
    {
        return view('platform.plans.create');
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $plan = Plan::query()->create($request->validated());

        return redirect()
            ->route('platform.plans.edit', $plan)
            ->with('success', 'Subscription plan created.');
    }

    public function edit(Plan $plan): View
    {
        return view('platform.plans.edit', compact('plan'));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return redirect()
            ->route('platform.plans.edit', $plan)
            ->with('success', 'Subscription plan updated.');
    }
}
