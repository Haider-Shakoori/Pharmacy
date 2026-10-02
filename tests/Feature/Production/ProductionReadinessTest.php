<?php

namespace Tests\Feature\Production;

use App\Models\PlatformAdmin;
use App\Services\Production\ProductionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_service_detects_safe_core_configuration(): void
    {
        $keyPair = sodium_crypto_sign_keypair();

        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://pharmacy.businessos.af',
            'pharmacy.deployment_host' => 'pharmacy.businessos.af',
            'pharmacy.provisioning.driver' => 'cpanel',
            'pharmacy.cpanel.host' => 'https://cpanel.example.test:2083',
            'pharmacy.cpanel.username' => 'businessos',
            'pharmacy.cpanel.api_token' => 'test-token',
            'pharmacy.cpanel.database_user' => 'businessos_pharmacy',
            'pharmacy.cpanel.verify_tls' => true,
            'pharmacy.security.hsts_enabled' => true,
            'pharmacy.license.signing_private_key' => base64_encode(
                sodium_crypto_sign_secretkey($keyPair),
            ),
            'pharmacy.license.signing_public_key' => base64_encode(
                sodium_crypto_sign_publickey($keyPair),
            ),
            'pharmacy.platform.bootstrap_admin.password' => null,
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'backup.schedule_enabled' => true,
            'backup.root' => storage_path('app/private/backups'),
        ]);

        PlatformAdmin::query()->create([
            'name' => 'Production Admin',
            'email' => 'production@example.test',
            'password' => 'strong-test-password',
            'is_active' => true,
        ]);

        $result = app(ProductionReadinessService::class)->check();

        $this->assertTrue($result['ready']);
        $this->assertSame(
            'warning',
            collect($result['checks'])
                ->firstWhere('name', 'database.production_driver')['status'],
        );
    }

    public function test_strict_command_fails_when_environment_is_not_production(): void
    {
        $this->artisan('pharmacy:production:check --strict')
            ->assertFailed();
    }

    public function test_central_readiness_endpoint_reports_database_availability(): void
    {
        $this->get('https://'.config('pharmacy.api_domain').'/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
    }
}
