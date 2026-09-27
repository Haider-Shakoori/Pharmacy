<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\TenantStatus;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_pharmacy_login_requires_matching_tenant_user_and_password(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul-pharmacy']);

        $user = app(TenantContext::class)->run($tenant, fn () => User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'secret-password',
            'is_active' => true,
        ]));

        $this->post('/pharmacy/login', [
            'tenant' => 'kabul-pharmacy',
            'email' => 'OWNER@example.test',
            'password' => 'secret-password',
        ])->assertRedirect('/pharmacy');

        $this->assertAuthenticatedAs($user);
        $this->assertSame($tenant->id, session('tenant_id'));
    }

    public function test_disabled_user_cannot_login(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul-pharmacy']);

        app(TenantContext::class)->run($tenant, fn () => User::query()->create([
            'name' => 'Disabled',
            'email' => 'disabled@example.test',
            'password' => 'secret-password',
            'is_active' => false,
        ]));

        $this->from('/pharmacy/login')->post('/pharmacy/login', [
            'tenant' => 'kabul-pharmacy',
            'email' => 'disabled@example.test',
            'password' => 'secret-password',
        ])
            ->assertRedirect('/pharmacy/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_tenant_cannot_login(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Suspended',
            'slug' => 'suspended',
            'status' => TenantStatus::Suspended,
        ]);

        app(TenantContext::class)->run($tenant, fn () => User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'secret-password',
        ]));

        $this->from('/pharmacy/login')->post('/pharmacy/login', [
            'tenant' => 'suspended',
            'email' => 'owner@example.test',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::withoutGlobalScope(TenantScope::class)->count());
        $this->assertGuest();
    }
}
