<?php

namespace App\Http\Controllers\Platform\Account;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('platform.account.password', [
            'admin' => $request->user('platform'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:platform'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        /** @var PlatformAdmin $admin */
        $admin = $request->user('platform');

        $admin->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return redirect()
            ->route('platform.account.password.edit')
            ->with('success', 'Your platform password has been changed successfully.');
    }
}
