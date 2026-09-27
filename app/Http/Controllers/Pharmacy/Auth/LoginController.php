<?php

namespace App\Http\Controllers\Pharmacy\Auth;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('pharmacy.auth.login');
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $validated = $request->validate([
            'tenant' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $tenant = Tenant::query()
            ->where('slug', Str::lower($validated['tenant']))
            ->where('status', TenantStatus::Active)
            ->first();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'tenant' => 'The pharmacy code or credentials are invalid.',
            ]);
        }

        $tenantContext->set($tenant);

        try {
            if (! Auth::guard('web')->attempt([
                'email' => Str::lower($validated['email']),
                'password' => $validated['password'],
                'is_active' => true,
            ], (bool) ($validated['remember'] ?? false))) {
                throw ValidationException::withMessages([
                    'email' => 'The pharmacy code or credentials are invalid.',
                ]);
            }

            $request->session()->regenerate();
            $request->session()->put('tenant_id', $tenant->id);

            Auth::guard('web')->user()?->forceFill([
                'last_login_at' => now(),
            ])->save();
        } finally {
            $tenantContext->clear();
        }

        return redirect()->intended(route('pharmacy.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pharmacy.login');
    }
}
