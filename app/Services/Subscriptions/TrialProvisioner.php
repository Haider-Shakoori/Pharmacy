<?php

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Support\Facades\DB;

class TrialProvisioner
{
    public function __construct(
        private readonly LicenseKeyService $licenseKeys,
    ) {}

    public function provision(Tenant $tenant): ?string
    {
        if ($tenant->subscription()->exists()) {
            return null;
        }

        return DB::transaction(function () use ($tenant): string {
            $plan = Plan::query()->firstOrCreate(
                ['code' => 'TRIAL'],
                [
                    'name' => '7-Day Trial',
                    'description' => 'Automatic evaluation plan for new pharmacies.',
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
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial,
                'trial_started_at' => $now,
                'trial_ends_at' => $now->copy()->addDays((int) config('pharmacy.trial.days', 7)),
                'starts_at' => $now,
                'auto_renew' => false,
                'notes' => 'Automatically provisioned new-pharmacy trial.',
            ]);

            return $this->licenseKeys->rotate($subscription);
        });
    }
}
