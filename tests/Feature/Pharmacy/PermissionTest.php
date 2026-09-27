<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_are_inherited_through_tenant_roles(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Kabul Pharmacy', 'slug' => 'kabul']);
        $permission = Permission::query()->create([
            'key' => 'daily-closing.reopen',
            'label' => 'Reopen daily closing',
            'group' => 'Daily Closing',
        ]);

        app(TenantContext::class)->run($tenant, function () use ($permission): void {
            $role = Role::query()->create(['name' => 'Supervisor', 'slug' => 'supervisor']);
            $role->permissions()->sync([$permission->id]);

            $user = User::query()->create([
                'name' => 'Supervisor',
                'email' => 'supervisor@example.test',
                'password' => 'password',
            ]);
            $user->roles()->sync([$role->id]);

            $this->assertTrue($user->hasPermission('daily-closing.reopen'));
            $this->assertFalse($user->hasPermission('roles.manage'));
        });
    }

    public function test_role_from_another_tenant_is_not_visible_in_current_tenant(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::query()->create(['name' => 'B', 'slug' => 'b']);

        $roleA = app(TenantContext::class)->run(
            $tenantA,
            fn () => Role::query()->create(['name' => 'Owner', 'slug' => 'owner']),
        );

        app(TenantContext::class)->run($tenantB, function () use ($roleA): void {
            $this->assertFalse(Role::query()->whereKey($roleA->id)->exists());
        });
    }
}
