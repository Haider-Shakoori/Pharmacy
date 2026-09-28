<?php

namespace Tests\Feature\Tenancy;

use App\Models\PlatformAdmin;
use App\Models\ProvisioningEvent;
use App\Models\Tenant;
use App\Services\Tenancy\TenantApplicationReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProvisioningArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'platform');
    }

    public function test_successful_provisioning_is_audited_and_clears_temporary_owner_secret(): void
    {
        $this->post('/platform/tenants', [
            'name' => 'Audit Pharmacy',
            'slug' => 'audit-pharmacy',
            'contact_person' => 'Audit Owner',
            'phone_whatsapp' => '+93700000000',
            'location' => 'Kabul',
            'timezone' => 'Asia/Kabul',
            'currency' => 'AFN',
            'locale' => 'en',
            'owner_name' => 'Audit Owner',
            'owner_email' => 'owner@audit.test',
            'owner_password' => 'secret-password',
        ])->assertRedirect();

        $tenant = Tenant::query()->whereHas('business', fn ($query) => $query->where('slug', 'audit-pharmacy'))->firstOrFail();

        $this->assertSame('application_ready', $tenant->provisioning_status);
        $this->assertNull($tenant->getAttribute('provisioning_owner_password'));
        $this->assertTrue(ProvisioningEvent::query()->where('tenant_id', $tenant->id)->where('step', 'database')->where('status', 'success')->exists());
        $this->assertTrue(ProvisioningEvent::query()->where('tenant_id', $tenant->id)->where('step', 'readiness')->where('status', 'success')->exists());
        $this->assertNull($tenant->subscription);
        $this->assertSame('application_ready', $tenant->provisioning_status);
    }

    public function test_non_local_readiness_requires_reachable_https_login(): void
    {
        config(['pharmacy.provisioning.driver' => 'cpanel']);

        Http::fake([
            'https://offline.pharmacy.businessos.af/login' => Http::response('Unavailable', 503),
            'https://ready.pharmacy.businessos.af/login' => Http::response('Login', 200),
        ]);

        $readiness = app(TenantApplicationReadiness::class);

        $this->assertFalse($readiness->isReady('offline.pharmacy.businessos.af'));
        $this->assertTrue($readiness->isReady('ready.pharmacy.businessos.af'));
    }
}
