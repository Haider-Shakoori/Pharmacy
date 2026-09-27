<?php

namespace App\Http\Controllers\Platform;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreTenantRequest;
use App\Http\Requests\Platform\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $tenants = Tenant::query()
            ->withCount('users')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(TenantStatus::tryFrom($status), fn ($query, TenantStatus $tenantStatus) => $query->where('status', $tenantStatus))
            ->orderBy('name')
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('platform.tenants.index', [
            'tenants' => $tenants,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('platform.tenants.create');
    }

    public function store(
        StoreTenantRequest $request,
        TrialProvisioner $trials,
        RbacProvisioner $rbac,
    ): RedirectResponse {
        $validated = $request->validated();

        [$tenant, $licenseKey] = DB::transaction(function () use ($validated, $trials, $rbac): array {
            $tenant = Tenant::query()->create(Arr::only($validated, [
                'name',
                'slug',
                'timezone',
                'currency',
                'locale',
            ]));

            $licenseKey = $trials->provision($tenant);

            $rbac->provisionOwner(
                $tenant,
                $validated['owner_name'],
                $validated['owner_email'],
                $validated['owner_password'],
            );

            return [$tenant, $licenseKey];
        });

        $response = redirect()
            ->route('platform.tenants.edit', $tenant)
            ->with('success', 'Pharmacy created with a 7-day trial and owner account.');

        if ($licenseKey !== null) {
            $response->with('generated_license_key', $licenseKey);
        }

        return $response;
    }

    public function edit(Tenant $tenant): View
    {
        return view('platform.tenants.edit', compact('tenant'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        return redirect()
            ->route('platform.tenants.edit', $tenant)
            ->with('success', 'Pharmacy tenant updated.');
    }
}
