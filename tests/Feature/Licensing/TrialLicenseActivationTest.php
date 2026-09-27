<?php

namespace Tests\Feature\Licensing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Licensing\LicenseKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialLicenseActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_device_lease_cannot_outlive_trial_end(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);

        $tenant = Tenant::query()->create(['name' => 'Trial Pharmacy', 'slug' => 'trial-pharmacy']);
        $plan = Plan::query()->create([
            'name' => 'Trial',
            'code' => 'TRIAL',
            'price' => 0,
            'billing_period' => 'monthly',
            'max_android_devices' => 1,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $trialEndsAt = now()->addDay();
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_started_at' => now(),
            'trial_ends_at' => $trialEndsAt,
            'starts_at' => now(),
            'auto_renew' => false,
        ]);
        $key = app(LicenseKeyService::class)->rotate($subscription);

        $response = $this->postJson('/api/v1/license/activate', [
            'license_key' => $key,
            'device_id' => '33333333-3333-4333-8333-333333333333',
        ])->assertOk();

        $this->assertSame('trial', $response->json('data.subscription_health'));
        $leaseEnd = \Carbon\CarbonImmutable::parse($response->json('data.lease_expires_at'));

        $this->assertTrue($leaseEnd->lessThanOrEqualTo($trialEndsAt));
    }
}
