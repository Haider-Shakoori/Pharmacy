<?php

namespace App\Http\Controllers\Pharmacy\Auth;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('pharmacy.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
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

        $user = $tenant === null
            ? null
            : User::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])
                ->where('is_active', true)
                ->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The pharmacy, email, or password is invalid.',
            ]);
        }

        $request->session()->put('tenant_id', $tenant->id);
        Auth::guard('web')->login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        if (in_array($user->preferred_locale, config('pharmacy.locales'), true)) {
            $request->session()->put('locale', $user->preferred_locale);
        }

        return redirect()->intended(route('pharmacy.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget(['tenant_id', 'locale']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pharmacy.login');
    }
}
