<?php

namespace App\Http\Controllers\Platform\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('platform.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $email = Str::lower($validated['email']);

        if (! Auth::guard('platform')->attempt([
            'email' => $email,
            'password' => $validated['password'],
            'is_active' => true,
        ], (bool) ($validated['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => 'The provided platform credentials are invalid.',
            ]);
        }

        $request->session()->regenerate();

        Auth::guard('platform')->user()?->forceFill([
            'last_login_at' => now(),
        ])->save();

        $dashboardRoute = $request->getHost() === config('pharmacy.deployment_host')
            && $request->is('platform*')
            && Route::has('legacy.platform.dashboard')
                ? 'legacy.platform.dashboard'
                : 'platform.dashboard';

        return redirect()->intended(route($dashboardRoute));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $loginRoute = $request->getHost() === config('pharmacy.deployment_host')
            && $request->is('platform*')
            && Route::has('legacy.platform.login')
                ? 'legacy.platform.login'
                : 'platform.login';

        return redirect()->route($loginRoute);
    }
}
