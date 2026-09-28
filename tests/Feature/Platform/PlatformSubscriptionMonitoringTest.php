<?php

namespace Tests\Feature\Platform;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\Subscription;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Subscriptions\PlatformSubscriptionMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSubscriptionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'monitor@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'platform');
    }

    public function test_monitoring_includes_missing_subscription_trial_deadline_and_stale_device_attention(): void
    {
        $noSubscription = $this->createTenant([
            'name' => 'No Subscription Pharmacy',
            'slug' => 'no-subscription',
        ]);

        $trialTenant = $this->createTenant([
            'name' => 'Trial Pharmacy',
            'slug' => 'trial-pharmacy',
        ]);
        $plan = Plan::query()->create([
            'name' => 'Trial Plan',
            'code' => 'MONITOR-TRIAL',
            'price' => 0,
            'billing_period' => 'monthly',
            'max_android_devices' => 2,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'business_id' => $trialTenant->business->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'trial_started_at' => now()->subDays(5),
            'trial_ends_at' => now()->addDay(),
            'starts_at' => now()->subDays(5),
            'auto_renew' => false,
        ]);

        app(LicenseKeyService::class)->rotate($subscription);
        $license = $subscription->license()->firstOrFail();

        LicenseActivation::query()->create([
            'license_id' => $license->id,
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Counter Android',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'activated_at' => now()->subDays(3),
            'last_seen_at' => now()->subDays(2),
        ]);

        $this->get('/platform/monitoring?state=attention')
            ->assertOk()
            ->assertSee('No Subscription Pharmacy')
            ->assertSee('No subscription')
            ->assertSee('Trial Pharmacy')
            ->assertSee('Trial ends within 2 days')
            ->assertSee('1 device(s) have not checked in for 24 hours');

        $this->get('/platform')
            ->assertOk()
            ->assertSee('Subscription attention')
            ->assertSee('Trial Pharmacy')
            ->assertSee('No Subscription Pharmacy');

        $this->assertNotNull($noSubscription->id);
    }

    public function test_monitoring_snapshot_uses_bounded_central_queries(): void
    {
        $this->createTenant(['name' => 'Query One', 'slug' => 'query-one']);
        $this->createTenant(['name' => 'Query Two', 'slug' => 'query-two']);
        $this->createTenant(['name' => 'Query Three', 'slug' => 'query-three']);

        $connection = DB::connection('central');
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        app(PlatformSubscriptionMonitoringService::class)->snapshot();

        $this->assertLessThanOrEqual(8, count($connection->getQueryLog()));
        $connection->disableQueryLog();
    }

    public function test_monitoring_marks_healthy_recent_device_as_operational_without_attention(): void
    {
        $tenant = $this->createTenant([
            'name' => 'Healthy Pharmacy',
            'slug' => 'healthy-pharmacy',
        ]);
        $plan = Plan::query()->create([
            'name' => 'Standard',
            'code' => 'MONITOR-STANDARD',
            'price' => 1000,
            'billing_period' => 'monthly',
            'max_android_devices' => 3,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $subscription = Subscription::query()->create([
            'business_id' => $tenant->business->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'auto_renew' => true,
        ]);

        $license = License::query()->create([
            'subscription_id' => $subscription->id,
            'key_hash' => hash('sha256', 'healthy-key'),
            'key_hint' => 'healthy…key',
            'status' => LicenseStatus::Active,
            'version' => 1,
            'generated_at' => now(),
        ]);

        LicenseActivation::query()->create([
            'license_id' => $license->id,
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Fresh Android',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'activated_at' => now()->subHours(2),
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $this->get('/platform/monitoring?state=operational&search=Healthy')
            ->assertOk()
            ->assertSee('Healthy Pharmacy')
            ->assertSee('Healthy')
            ->assertSee('No action required')
            ->assertSee('1 / 3 active');
    }
}
