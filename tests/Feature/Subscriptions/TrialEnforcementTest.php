<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionHealth;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Subscriptions\SubscriptionHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_elapsed_trial_is_non_operational_and_expiration_command_revokes_license(): void
    {
        $tenant = $this->createTenant(['name' => 'Expired Trial', 'slug' => 'expired-trial']);
        $plan = Plan::query()->create([
            'name' => 'Trial',
            'code' => 'TRIAL',
            'price' => 0,
            'billing_period' => 'monthly',
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $subscription = Subscription::query()->create([
            'business_id' => $tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_started_at' => now()->subDays(8),
            'trial_ends_at' => now()->subDay(),
            'starts_at' => now()->subDays(8),
            'auto_renew' => false,
        ]);

        app(LicenseKeyService::class)->rotate($subscription);

        $this->assertSame(SubscriptionHealth::Expired, app(SubscriptionHealthService::class)->forTenant($tenant));

        $this->artisan('subscriptions:expire-trials')->assertSuccessful();

        $subscription->refresh();
        $subscription->license->refresh();

        $this->assertSame('expired', $subscription->status->value);
        $this->assertSame(LicenseStatus::Revoked, $subscription->license->status);
    }
}
