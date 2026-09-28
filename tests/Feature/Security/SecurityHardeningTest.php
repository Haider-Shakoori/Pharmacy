<?php

namespace Tests\Feature\Security;

use App\Models\PlatformAdmin;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        RateLimiter::clear('unused');
    }

    public function test_security_headers_are_present_on_platform_and_tenant_responses(): void
    {
        $this->withServerVariables([
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ]);

        $this->get('/platform/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Strict-Transport-Security');

        $tenant = $this->createTenant([
            'name' => 'Secure Pharmacy',
            'slug' => 'secure-pharmacy',
        ]);

        $this->onTenantDomain($tenant)
            ->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_session_storage_is_encrypted_by_default(): void
    {
        $this->assertTrue((bool) config('session.encrypt'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue((bool) config('session.http_only'));
    }

    public function test_platform_login_rate_limit_is_keyed_and_enforced(): void
    {
        PlatformAdmin::query()->create([
            'name' => 'Security Admin',
            'email' => 'security@example.test',
            'password' => 'correct-password',
            'is_active' => true,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/platform/login')
                ->post('/platform/login', [
                    'email' => 'security@example.test',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect('/platform/login');
        }

        $this->post('/platform/login', [
            'email' => 'security@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_pharmacy_login_rate_limit_is_isolated_by_tenant_host(): void
    {
        $tenantA = $this->createTenant([
            'name' => 'Secure A',
            'slug' => 'secure-a',
        ]);
        $tenantB = $this->createTenant([
            'name' => 'Secure B',
            'slug' => 'secure-b',
        ]);

        app(TrialProvisioner::class)->provision($tenantA);
        app(TrialProvisioner::class)->provision($tenantB);
        app(RbacProvisioner::class)->provisionOwner(
            $tenantA,
            'Owner A',
            'owner@example.test',
            'password-a',
        );
        app(RbacProvisioner::class)->provisionOwner(
            $tenantB,
            'Owner B',
            'owner@example.test',
            'password-b',
        );

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->onTenantDomain($tenantA)
                ->from('/login')
                ->post('/login', [
                    'email' => 'owner@example.test',
                    'password' => 'wrong',
                ])
                ->assertRedirect('/login');
        }

        $this->onTenantDomain($tenantA)
            ->post('/login', [
                'email' => 'owner@example.test',
                'password' => 'wrong',
            ])
            ->assertTooManyRequests();

        $this->onTenantDomain($tenantB)
            ->post('/login', [
                'email' => 'owner@example.test',
                'password' => 'password-b',
            ])
            ->assertRedirect('/');
    }

    public function test_oversized_api_payload_is_rejected_before_validation(): void
    {
        config(['pharmacy.security.max_api_payload_bytes' => 1024]);

        $this->postJson('/api/v1/mobile/register', [
            'license_key' => str_repeat('A', 1500),
            'device_id' => '11111111-1111-4111-8111-111111111111',
            'email' => 'mobile@example.test',
            'password' => 'password',
        ])
            ->assertStatus(413)
            ->assertJsonPath('max_bytes', 1024);
    }
}
