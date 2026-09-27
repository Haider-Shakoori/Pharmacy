<?php

namespace App\Http\Controllers\Pharmacy\Auth;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        abort_unless(tenant()?->status === TenantStatus::Active, 403, 'This pharmacy is not active.');

        if (! Auth::guard('web')->attempt([
            'email' => Str::lower($validated['email']),
            'password' => $validated['password'],
            'is_active' => true,
        ], (bool) ($validated['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are invalid.',
            ]);
        }

        $request->session()->regenerate();

        Auth::guard('web')->user()?->forceFill([
            'last_login_at' => now(),
        ])->save();

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
