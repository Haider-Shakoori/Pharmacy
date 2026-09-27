<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\RbacProvisioner;
use App\Services\Subscriptions\TrialProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_cashier_can_close_day_but_cannot_reopen_or_manage_users(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        app(TrialProvisioner::class)->provision($tenant);
        $roles = app(RbacProvisioner::class)->ensureForTenant($tenant);

        $cashier = app(TenantContext::class)->run($tenant, function () use ($roles): User {
            $user = User::query()->create([
                'name' => 'Cashier',
                'email' => 'cashier@example.test',
                'password' => 'password123',
            ]);
            $user->roles()->sync([$roles['cashier']->id]);

            return $user;
        });

        app(TenantContext::class)->set($tenant);

        $this->assertTrue($cashier->hasPermission('daily_closing.perform'));
        $this->assertFalse($cashier->hasPermission('daily_closing.reopen'));
        $this->assertFalse($cashier->hasPermission('users.manage'));
    }

    public function test_owner_has_daily_closing_reopen_and_access_management_permissions(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Owner Pharmacy', 'slug' => 'owner-pharmacy']);
        app(TrialProvisioner::class)->provision($tenant);
        $owner = app(RbacProvisioner::class)->provisionOwner($tenant, 'Owner', 'owner@example.test', 'password123');

        app(TenantContext::class)->set($tenant);

        $this->assertTrue($owner->hasPermission('daily_closing.perform'));
        $this->assertTrue($owner->hasPermission('daily_closing.reopen'));
        $this->assertTrue($owner->hasPermission('users.manage'));
        $this->assertTrue($owner->hasPermission('roles.manage'));
    }
}
