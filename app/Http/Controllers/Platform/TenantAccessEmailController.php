<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str as MessageStr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Throwable;

class TenantAccessEmailController extends Controller
{
    public function __invoke(Tenant $tenant): RedirectResponse
    {
        $tenant->loadMissing('business');
        if ($tenant->provisioning_status !== 'application_ready' || ! $tenant->business) {
            return back()->withErrors(['email' => 'The pharmacy must be ready before sending access instructions.']);
        }

        $email = $tenant->business->owner_email;
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => 'Owner email is invalid.']);
        }

        $login = 'https://'.$tenant->business->slug.'.'.config('pharmacy.tenant_domain').'/login';
        $token = Str::random(48);
        Cache::put('pharmacy-access:'.$token, $email, now()->addMinutes(30));
        $setup = URL::temporarySignedRoute('pharmacy.access.setup', now()->addMinutes(30), [
            'pharmacy' => $tenant->business->slug,
            'email' => $email,
            'token' => $token,
        ]);

        $attemptId = (string) MessageStr::uuid();
        DB::connection('central')->table('pharmacy_access_email_attempts')->insert([
            'id' => $attemptId, 'tenant_id' => $tenant->id, 'recipient' => $email,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            Mail::send('emails.pharmacy-access', compact('login', 'email', 'setup'),
                fn ($message) => $message->to($email)->subject('Welcome to Darmaltoon — Your pharmacy access'));
            DB::connection('central')->table('pharmacy_access_email_attempts')
                ->where('id', $attemptId)->update([
                    'status' => 'submitted', 'submitted_at' => now(), 'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            DB::connection('central')->table('pharmacy_access_email_attempts')
                ->where('id', $attemptId)->update([
                    'status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500),
                    'updated_at' => now(),
                ]);
            report($exception);
            return back()->withErrors(['email' => 'Email could not be submitted. Check mail settings.']);
        }

        return back()->with('success', 'Access instructions submitted to '.$email.'. Check cPanel Track Delivery.');
    }
}
