<?php

namespace Tests\Feature\Licensing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Licensing\LicenseKeyService;
use Carbon\CarbonImmutable;
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

        $tenant = $this->createTenant(['name' => 'Trial Pharmacy', 'slug' => 'trial-pharmacy']);
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
            'business_id' => $tenant->business->id,
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
        $leaseEnd = CarbonImmutable::parse($response->json('data.lease_expires_at'));

        $this->assertTrue($leaseEnd->lessThanOrEqualTo($trialEndsAt));
    }

    public function test_owner_can_start_one_time_trial_and_register_one_windows_pc_without_license_key(): void
    {
        $this->configureSigningKeys();

        $tenant = $this->createTenant([
            'name' => 'Windows Trial Pharmacy',
            'slug' => 'windows-trial',
            'owner_email' => 'owner@windows-trial.test',
        ]);
        $this->createOwner($tenant, 'owner@windows-trial.test', 'password123');

        $first = $this->postJson('/api/v1/mobile/trial/register', [
            'pharmacy_code' => 'windows-trial',
            'device_id' => '55555555-5555-4555-8555-555555555555',
            'platform' => 'windows',
            'device_name' => 'Counter PC',
            'email' => 'owner@windows-trial.test',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription_health', 'trial')
            ->assertJsonPath('data.plan.code', 'TRIAL')
            ->assertJsonPath('data.plan.max_windows_devices', 1);

        $tenant->refresh()->load('business.subscription');
        $trialEndsAt = $tenant->business->subscription->trial_ends_at;
        $this->assertNotNull($tenant->business->trial_used_at);
        $this->assertNotNull($trialEndsAt);

        $this->assertDatabaseHas('license_activations', [
            'device_id' => '55555555-5555-4555-8555-555555555555',
            'platform' => 'windows',
            'revoked_at' => null,
        ]);

        $leaseEnd = CarbonImmutable::parse($first->json('data.offline_lease.expires_at'));
        $this->assertTrue($leaseEnd->lessThanOrEqualTo($trialEndsAt));

        $this->postJson('/api/v1/mobile/trial/register', [
            'pharmacy_code' => 'windows-trial',
            'device_id' => '55555555-5555-4555-8555-555555555555',
            'platform' => 'windows',
            'device_name' => 'Counter PC',
            'email' => 'owner@windows-trial.test',
            'password' => 'password123',
        ])->assertOk();

        $tenant->refresh()->load('business.subscription');
        $this->assertTrue(
            $tenant->business->subscription->trial_ends_at->equalTo($trialEndsAt),
        );

        $this->postJson('/api/v1/mobile/trial/register', [
            'pharmacy_code' => 'windows-trial',
            'device_id' => '66666666-6666-4666-8666-666666666666',
            'platform' => 'windows',
            'device_name' => 'Second PC',
            'email' => 'owner@windows-trial.test',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_id');
    }

    public function test_invalid_owner_credentials_do_not_consume_the_trial(): void
    {
        $this->configureSigningKeys();

        $tenant = $this->createTenant([
            'name' => 'Protected Trial Pharmacy',
            'slug' => 'protected-trial',
            'owner_email' => 'owner@protected-trial.test',
        ]);
        $this->createOwner($tenant, 'owner@protected-trial.test', 'password123');

        $this->postJson('/api/v1/mobile/trial/register', [
            'pharmacy_code' => 'protected-trial',
            'device_id' => '77777777-7777-4777-8777-777777777777',
            'platform' => 'windows',
            'email' => 'owner@protected-trial.test',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $tenant->refresh()->load('business.subscription');
        $this->assertNull($tenant->business->trial_used_at);
        $this->assertNull($tenant->business->subscription);
    }

    private function configureSigningKeys(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);
    }

    private function createOwner($tenant, string $email, string $password): void
    {
        $tenant->run(function () use ($tenant, $email, $password): void {
            $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);
            $owner = User::query()->create([
                'name' => 'Trial Owner',
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);
            $owner->roles()->sync([$roles['owner']->id]);
        });
    }
}
