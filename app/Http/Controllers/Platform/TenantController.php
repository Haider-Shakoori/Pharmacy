<?php

namespace App\Http\Controllers\Platform;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreTenantRequest;
use App\Http\Requests\Platform\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $tenants = Tenant::query()
            ->with(['business', 'domains'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('business', function ($query) use ($search): void {
                    $query->where('pharmacy_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('owner_email', 'like', "%{$search}%")
                        ->orWhere('phone_whatsapp', 'like', "%{$search}%");
                });
            })
            ->when(TenantStatus::tryFrom($status), fn ($query, TenantStatus $tenantStatus) => $query->where('status', $tenantStatus))
            ->latest()
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('platform.tenants.index', compact('tenants', 'search', 'status'));
    }

    public function create(): View
    {
        return view('platform.tenants.create');
    }

    public function store(
        StoreTenantRequest $request,
        TenantProvisioningService $provisioner,
    ): RedirectResponse {
        [$tenant, $licenseKey] = $provisioner->provision($request->validated());

        $response = redirect()
            ->route('platform.tenants.edit', $tenant)
            ->with(
                'success',
                $tenant->provisioning_status === 'application_ready'
                    ? 'Pharmacy application provisioned with isolated database, domain, and owner account. The hosted trial can now be started explicitly.'
                    : 'Pharmacy database and owner are provisioned; the hosted trial becomes available after domain/TLS readiness.',
            );

        if ($licenseKey !== null) {
            $response->with('generated_license_key', $licenseKey);
        }

        return $response;
    }

    public function edit(Tenant $tenant): View
    {
        $tenant->load(['business.subscription.plan', 'business.subscription.license', 'domains', 'provisioningEvents']);

        return view('platform.tenants.edit', compact('tenant'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validated();

        DB::connection('central')->transaction(function () use ($tenant, $validated): void {
            $tenant->business()->firstOrFail()->update([
                'pharmacy_name' => $validated['name'],
                'slug' => $validated['slug'],
                'contact_person' => $validated['contact_person'],
                'phone_whatsapp' => $validated['phone_whatsapp'],
                'location' => $validated['location'],
                'billing_currency' => strtoupper($validated['currency']),
                'default_timezone' => $validated['timezone'],
                'default_locale' => $validated['locale'],
            ]);

            $domain = $validated['slug'].'.'.config('pharmacy.tenant_domain');
            $tenant->domains()->first()?->update(['domain' => $domain]);
        });

        return redirect()
            ->route('platform.tenants.edit', $tenant)
            ->with('success', 'Pharmacy commercial profile updated.');
    }
}
