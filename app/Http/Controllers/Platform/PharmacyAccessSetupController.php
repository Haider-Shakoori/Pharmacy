<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\Password;

class PharmacyAccessSetupController extends Controller
{
    public function show(Request $request)
    {
        $email = tenancy()->central(fn () => Cache::get('pharmacy-access:'.$request->query('token')));
        abort_unless(is_string($email) && $email === $request->query('email'), 403);

        return view('platform.tenants.access-setup', ['login' => route('pharmacy.login')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(12)]]);
        $key = 'pharmacy-access:'.$request->query('token');
        $email = tenancy()->central(fn () => Cache::get($key));
        abort_unless(is_string($email) && $email === $request->query('email'), 403);
        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        tenancy()->central(fn () => Cache::forget($key));

        return redirect()->route('pharmacy.login')->with('status', 'Your password has been set. Please sign in.');
    }
}
