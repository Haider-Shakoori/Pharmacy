<?php

namespace Tests\Feature\Licensing;

use App\Models\LocalNodeState;
use App\Services\Licensing\LocalNodeLeaseGuard;
use App\Services\Licensing\OfflineLeaseSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LocalNodeLeaseGuardTest extends TestCase
{
    use RefreshDatabase;

    private string $clockFile;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        config([
            'pharmacy.license.signing_private_key' => base64_encode(
                sodium_crypto_sign_secretkey($keyPair),
            ),
            'pharmacy.license.signing_public_key' => base64_encode(
                sodium_crypto_sign_publickey($keyPair),
            ),
        ]);

        $this->clockFile = sys_get_temp_dir().'/businessos-pharmacy-clock-'.uniqid().'.dat';
        config([
            'pharmacy.local_node.clock_state_file' => $this->clockFile,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->clockFile);

        parent::tearDown();
    }

    public function test_valid_signed_local_lease_is_operational(): void
    {
        $tenant = $this->createTenant(['slug' => 'local-node-valid']);
        $deviceId = '11111111-1111-4111-8111-111111111111';
        $issuedAt = now()->subMinute();
        $expiresAt = now()->addHour();
        $token = $this->lease(
            (string) $tenant->id,
            $deviceId,
            $issuedAt->getTimestamp(),
            $expiresAt->getTimestamp(),
        );

        LocalNodeState::query()->create([
            'tenant_id' => $tenant->id,
            'device_id' => $deviceId,
            'lease_token' => $token,
            'lease_public_key' => (string) config('pharmacy.license.signing_public_key'),
            'lease_issued_at' => $issuedAt,
            'lease_expires_at' => $expiresAt,
            'last_observed_at' => now()->subSecond(),
        ]);

        app(LocalNodeLeaseGuard::class)->assertOperational($tenant);

        $this->assertFileExists($this->clockFile);
        $this->assertGreaterThan(
            0,
            (int) file_get_contents($this->clockFile),
        );
    }

    public function test_tampered_database_expiry_is_rejected(): void
    {
        $tenant = $this->createTenant(['slug' => 'local-node-tampered']);
        $deviceId = '22222222-2222-4222-8222-222222222222';
        $issuedAt = now()->subMinute();
        $signedExpiry = now()->addHour();
        $token = $this->lease(
            (string) $tenant->id,
            $deviceId,
            $issuedAt->getTimestamp(),
            $signedExpiry->getTimestamp(),
        );

        LocalNodeState::query()->create([
            'tenant_id' => $tenant->id,
            'device_id' => $deviceId,
            'lease_token' => $token,
            'lease_public_key' => (string) config('pharmacy.license.signing_public_key'),
            'lease_issued_at' => $issuedAt,
            'lease_expires_at' => $signedExpiry->copy()->addHour(),
            'last_observed_at' => now()->subSecond(),
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('stored offline license expiry is invalid');

        app(LocalNodeLeaseGuard::class)->assertOperational($tenant);
    }

    public function test_clock_rollback_beyond_tolerance_is_rejected(): void
    {
        $tenant = $this->createTenant(['slug' => 'local-node-clock']);
        $deviceId = '33333333-3333-4333-8333-333333333333';
        $issuedAt = now()->subMinute();
        $expiresAt = now()->addHours(2);
        $token = $this->lease(
            (string) $tenant->id,
            $deviceId,
            $issuedAt->getTimestamp(),
            $expiresAt->getTimestamp(),
        );

        LocalNodeState::query()->create([
            'tenant_id' => $tenant->id,
            'device_id' => $deviceId,
            'lease_token' => $token,
            'lease_public_key' => (string) config('pharmacy.license.signing_public_key'),
            'lease_issued_at' => $issuedAt,
            'lease_expires_at' => $expiresAt,
            'last_observed_at' => now()->addMinutes(10),
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('clock moved backwards');

        app(LocalNodeLeaseGuard::class)->assertOperational($tenant);
    }

    private function lease(
        string $tenantId,
        string $deviceId,
        int $issuedAt,
        int $expiresAt,
    ): string {
        return app(OfflineLeaseSigner::class)->sign([
            'v' => 1,
            'tenant_id' => $tenantId,
            'device_id' => $deviceId,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ]);
    }
}