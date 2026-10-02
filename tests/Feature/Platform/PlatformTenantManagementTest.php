<?php

namespace Tests\Feature\Platform;

use App\Enums\TenantStatus;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTenantManagementTest extends TestCase
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

    public function test_platform_admin_can_create_update_and_suspend_a_pharmacy(): void
    {
        $this->post('/platform/tenants', [
            'name' => 'Kabul Central Pharmacy',
            'slug' => 'kabul-central',
            'contact_person' => 'Kabul Contact',
            'phone_whatsapp' => '+93700000000',
            'location' => 'Kabul',
            'timezone' => 'Asia/Kabul',
            'currency' => 'AFN',
            'locale' => 'fa',
            'owner_name' => 'Kabul Owner',
            'owner_email' => 'owner@kabul.test',
            'owner_password' => 'password123',
        ])->assertRedirect();

        $tenant = Tenant::query()->whereHas('business', fn ($query) => $query->where('slug', 'kabul-central'))->firstOrFail();

        $this->assertSame('application_ready', $tenant->provisioning_status);
        $this->assertSame('kabul-central.'.config('pharmacy.deployment_host'), $tenant->domains()->value('domain'));
        app(TenantContext::class)->run($tenant, function (): void {
            $this->assertTrue(User::query()->where('email', 'owner@kabul.test')->exists());
        });

        $this->put("/platform/tenants/{$tenant->id}", [
            'name' => 'Kabul Central Pharmacy Updated',
            'slug' => 'kabul-central',
            'contact_person' => 'Updated Contact',
            'phone_whatsapp' => '+93711111111',
            'location' => 'Kabul City',
            'timezone' => 'Asia/Kabul',
            'currency' => 'AFN',
            'locale' => 'ps',
        ])->assertRedirect();

        $this->put("/platform/tenants/{$tenant->id}/status/suspended")
            ->assertRedirect();

        $tenant->refresh();

        $this->assertSame('Kabul Central Pharmacy Updated', $tenant->name);
        $this->assertSame('ps', $tenant->locale);
        $this->assertSame(TenantStatus::Suspended, $tenant->status);
    }

    public function test_tenant_search_is_server_side_and_filterable(): void
    {
        $this->createTenant(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        $this->createTenant([
            'name' => 'Herat Pharmacy',
            'slug' => 'herat',
            'status' => TenantStatus::Suspended,
        ]);

        $this->get('/platform/tenants?search=Kabul')
            ->assertOk()
            ->assertSee('Kabul Pharmacy')
            ->assertDontSee('Herat Pharmacy');

        $this->get('/platform/tenants?status=suspended')
            ->assertOk()
            ->assertSee('Herat Pharmacy')
            ->assertDontSee('Kabul Pharmacy');
    }

    public function test_reserved_or_dns_invalid_subdomain_cannot_be_assigned_to_a_pharmacy(): void
    {
        $payload = [
            'name' => 'Reserved Pharmacy',
            'slug' => 'platform',
            'contact_person' => 'Reserved Contact',
            'phone_whatsapp' => '+93700000001',
            'location' => 'Kabul',
            'timezone' => 'Asia/Kabul',
            'currency' => 'AFN',
            'locale' => 'en',
            'owner_name' => 'Reserved Owner',
            'owner_email' => 'reserved@example.test',
            'owner_password' => 'password123',
        ];

        $this->post('/platform/tenants', $payload)
            ->assertSessionHasErrors('slug');

        $payload['slug'] = 'invalid_slug';
        $payload['owner_email'] = 'invalid@example.test';

        $this->post('/platform/tenants', $payload)
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('tenants', 0);
    }
}
