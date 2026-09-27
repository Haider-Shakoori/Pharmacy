<?php

namespace Tests\Feature\Platform;

use App\Enums\TenantStatus;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
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
            'timezone' => 'Asia/Kabul',
            'currency' => 'AFN',
            'locale' => 'fa',
        ])->assertRedirect();

        $tenant = Tenant::query()->where('slug', 'kabul-central')->firstOrFail();

        $this->put("/platform/tenants/{$tenant->id}", [
            'name' => 'Kabul Central Pharmacy Updated',
            'slug' => 'kabul-central',
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
        Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        Tenant::query()->create([
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
}
