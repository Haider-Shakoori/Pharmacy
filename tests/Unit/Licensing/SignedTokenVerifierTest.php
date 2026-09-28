<?php

namespace Tests\Unit\Licensing;

use App\Services\Licensing\OfflineLeaseSigner;
use App\Services\Licensing\SignedTokenVerifier;
use Illuminate\Auth\AuthenticationException;
use Tests\TestCase;

class SignedTokenVerifierTest extends TestCase
{
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
    }

    public function test_valid_mobile_access_token_verifies(): void
    {
        $now = now()->getTimestamp();
        $token = app(OfflineLeaseSigner::class)->sign([
            'v' => 1,
            'purpose' => 'mobile_access',
            'issued_at' => $now,
            'expires_at' => $now + 600,
        ]);

        $payload = app(SignedTokenVerifier::class)->verify(
            $token,
            purpose: 'mobile_access',
        );

        $this->assertSame(1, $payload['v']);
    }

    public function test_future_issued_token_is_rejected(): void
    {
        config(['pharmacy.security.token_clock_skew_seconds' => 30]);
        $now = now()->getTimestamp();
        $token = app(OfflineLeaseSigner::class)->sign([
            'v' => 1,
            'purpose' => 'mobile_access',
            'issued_at' => $now + 120,
            'expires_at' => $now + 600,
        ]);

        $this->expectException(AuthenticationException::class);

        app(SignedTokenVerifier::class)->verify(
            $token,
            purpose: 'mobile_access',
        );
    }

    public function test_token_with_invalid_lifetime_is_rejected(): void
    {
        $now = now()->getTimestamp();
        $token = app(OfflineLeaseSigner::class)->sign([
            'v' => 1,
            'purpose' => 'mobile_access',
            'issued_at' => $now,
            'expires_at' => $now,
        ]);

        $this->expectException(AuthenticationException::class);

        app(SignedTokenVerifier::class)->verify(
            $token,
            purpose: 'mobile_access',
        );
    }

    public function test_oversized_token_is_rejected_before_signature_work(): void
    {
        config(['pharmacy.security.max_signed_token_bytes' => 32]);

        $this->expectException(AuthenticationException::class);

        app(SignedTokenVerifier::class)->verify(
            str_repeat('A', 64),
            purpose: 'mobile_access',
        );
    }
}
