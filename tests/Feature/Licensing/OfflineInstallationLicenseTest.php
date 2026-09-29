<?php

namespace Tests\Feature\Licensing;

use App\Models\LicenseActivation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Licensing\LicenseKeyService;
use App\Services\Licensing\SignedTokenVerifier;
use App\Services\Offline\OfflineInstallationIdentity;
use App\Services\Offline\OfflineLicenseManager;
use App\Services\Offline\OfflineLicenseStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineInstallationLicenseTest extends TestCase
{
    use RefreshDatabase;

    private string $dataRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dataRoot = storage_path('framework/testing/offline-'.Str::uuid());
        File::ensureDirectoryExists($this->dataRoot);

        $keypair = sodium_crypto_sign_keypair();

        config([
            'offline.enabled' => false,
            'offline.data_root' => $this->dataRoot,
            'offline.machine_fingerprint_override' => 'test-machine-a',
            'pharmacy.license.signing_private_key' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
            'pharmacy.license.signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dataRoot);
        parent::tearDown();
    }

    public function test_central_server_issues_machine_bound_expiring_offline_license(): void
    {
        [$licenseKey, $subscription] = $this->licensedSubscription();
        $identity = app(OfflineInstallationIdentity::class);

        $response = $this->postJson('/api/v1/offline/license/activate', [
            'license_key' => $licenseKey,
            'installation_id' => $identity->installationId(),
            'machine_fingerprint_hash' => $identity->machineFingerprintHash(),
            'device_name' => 'Front Counter PC',
            'app_version' => '1.0.0',
        ])->assertOk();

        $token = $response->json('data.license_token');
        $payload = app(SignedTokenVerifier::class)->verify($token, 'offline_installation');

        $this->assertSame('businessos-pharmacy', $payload['product']);
        $this->assertSame('offline', $payload['edition']);
        $this->assertSame($identity->installationId(), $payload['installation_id']);
        $this->assertSame($identity->machineFingerprintHash(), $payload['machine_fingerprint_hash']);
        $this->assertSame($subscription->ends_at->getTimestamp(), $payload['expires_at']);

        $activation = LicenseActivation::query()
            ->where('device_id', $identity->installationId())
            ->firstOrFail();

        $this->assertSame('windows_offline', $activation->platform);
        $this->assertSame($identity->machineFingerprintHash(), $activation->machine_fingerprint_hash);
    }

    public function test_cloud_only_plan_cannot_activate_offline_installation(): void
    {
        [$licenseKey, $subscription] = $this->licensedSubscription();
        $subscription->plan()->update(['features' => ['advanced_reports']]);
        $identity = app(OfflineInstallationIdentity::class);

        $this->postJson('/api/v1/offline/license/activate', [
            'license_key' => $licenseKey,
            'installation_id' => $identity->installationId(),
            'machine_fingerprint_hash' => $identity->machineFingerprintHash(),
            'device_name' => 'Cloud Only PC',
            'app_version' => '1.0.0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('license_key');
    }

    public function test_local_installation_verifies_signature_machine_and_license_file_without_internet(): void
    {
        [$licenseKey] = $this->licensedSubscription();
        $identity = app(OfflineInstallationIdentity::class);

        $token = $this->postJson('/api/v1/offline/license/activate', [
            'license_key' => $licenseKey,
            'installation_id' => $identity->installationId(),
            'machine_fingerprint_hash' => $identity->machineFingerprintHash(),
            'device_name' => 'Offline PC',
            'app_version' => '1.0.0',
        ])->assertOk()->json('data.license_token');

        app(OfflineLicenseStore::class)->write($token);
        config(['offline.enabled' => true]);

        $status = app(OfflineLicenseManager::class)->status();

        $this->assertTrue($status['valid']);
        $this->assertSame('OFFLINE', $status['payload']['plan_code']);

        config(['offline.machine_fingerprint_override' => 'different-computer']);

        $this->assertFalse(app(OfflineLicenseManager::class)->status()['valid']);
    }

    public function test_offline_http_mode_allows_activation_page_but_blocks_application_without_license(): void
    {
        config(['offline.enabled' => true]);

        $this->get('/offline/license')
            ->assertOk()
            ->assertSee('License activation');

        $this->get('/platform')
            ->assertRedirect(route('offline.license.show'));
    }

    private function licensedSubscription(): array
    {
        $tenant = $this->createTenant([
            'name' => 'Offline Test Pharmacy',
            'slug' => 'offline-test',
        ]);

        $plan = Plan::query()->create([
            'name' => 'Offline Annual',
            'code' => 'OFFLINE',
            'description' => 'Offline Windows annual license.',
            'price' => 12000,
            'currency' => 'AFN',
            'billing_period' => 'yearly',
            'max_users' => 10,
            'max_android_devices' => 5,
            'max_branches' => 1,
            'offline_grace_days' => 7,
            'features' => ['offline_windows'],
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $subscription = Subscription::query()->create([
            'business_id' => $tenant->business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear()->startOfSecond(),
            'auto_renew' => false,
        ]);

        $licenseKey = app(LicenseKeyService::class)->rotate($subscription);

        return [$licenseKey, $subscription->fresh()];
    }
}
