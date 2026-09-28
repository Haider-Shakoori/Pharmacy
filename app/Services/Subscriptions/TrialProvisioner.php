<?php

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialProvisioner
{
    public function __construct(
        private readonly LicenseKeyService $licenseKeys,
    ) {}

    public function provision(Tenant $tenant): string
    {
        $tenant->refresh()->loadMissing('business');

        if ($tenant->provisioning_status !== 'application_ready') {
            throw ValidationException::withMessages([
                'trial' => 'The pharmacy must be fully provisioned before its hosted trial can start.',
            ]);
        }

        return DB::connection('central')->transaction(function () use ($tenant): string {
            $business = Business::query()->lockForUpdate()->findOrFail($tenant->business->id);

            if ($business->trial_used_at !== null) {
                throw ValidationException::withMessages([
                    'trial' => 'This pharmacy has already used its hosted trial.',
                ]);
            }

            if ($business->subscription()->exists()) {
                throw ValidationException::withMessages([
                    'trial' => 'This pharmacy already has a subscription. Cancel or manage it instead of starting a trial.',
                ]);
            }

            $plan = Plan::query()->firstOrCreate(
                ['code' => 'TRIAL'],
                [
                    'name' => '7-Day Trial',
                    'description' => 'One-time hosted evaluation plan for newly provisioned pharmacies.',
                    'price' => 0,
                    'currency' => 'AFN',
                    'billing_period' => 'monthly',
                    'max_users' => 5,
                    'max_android_devices' => 2,
                    'max_branches' => 1,
                    'offline_grace_days' => (int) config('pharmacy.trial.days', 7),
                    'features' => [],
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            $now = now();
            $subscription = Subscription::query()->create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial,
                'trial_started_at' => $now,
                'trial_ends_at' => $now->copy()->addDays((int) config('pharmacy.trial.days', 7)),
                'starts_at' => $now,
                'auto_renew' => false,
                'notes' => 'One-time hosted trial started by the platform operator after provisioning.',
            ]);

            $business->update(['trial_used_at' => $now]);

            return $this->licenseKeys->rotate($subscription);
        });
    }
}
