<?php

namespace App\Services\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RbacProvisioner
{
    public const PERMISSIONS = [
        'dashboard.view' => 'View dashboard',
        'users.manage' => 'Manage pharmacy users',
        'roles.manage' => 'Manage roles and permissions',
        'medicines.manage' => 'Manage medicines',
        'inventory.manage' => 'View and manage inventory',
        'inventory.adjust' => 'Post inventory adjustments',
        'inventory.status' => 'Quarantine recall or release batches',
        'purchases.manage' => 'Prepare suppliers and purchases',
        'purchases.approve' => 'Approve purchase orders',
        'purchases.pay' => 'Record supplier payments',
        'pos.sell' => 'Use point of sale',
        'pos.discount' => 'Apply POS discounts',
        'pos.price_override' => 'Override POS sale price',
        'returns.manage' => 'Manage returns',
        'daily_closing.perform' => 'Perform Daily Closing',
        'daily_closing.approve' => 'Approve Daily Closing',
        'daily_closing.reopen' => 'Reopen a finalized Daily Closing',
        'reports.view' => 'View reports',
        'accounting.manage' => 'Manage accounting',
        'settings.manage' => 'Manage pharmacy settings',
        'sync.manage' => 'Manage devices and synchronization',
    ];

    private const ROLE_PERMISSIONS = [
        'owner' => '*',
        'administrator' => '*',
        'pharmacist' => [
            'dashboard.view', 'medicines.manage', 'inventory.manage',
            'inventory.status', 'pos.sell', 'pos.discount', 'returns.manage',
            'daily_closing.perform', 'reports.view',
        ],
        'cashier' => ['dashboard.view', 'pos.sell', 'returns.manage', 'daily_closing.perform'],
        'inventory' => [
            'dashboard.view', 'medicines.manage', 'inventory.manage',
            'inventory.adjust', 'inventory.status', 'purchases.manage', 'reports.view',
        ],
        'purchaser' => ['dashboard.view', 'medicines.manage', 'purchases.manage', 'reports.view'],
        'accountant' => [
            'dashboard.view', 'purchases.pay', 'daily_closing.perform', 'daily_closing.approve',
            'daily_closing.reopen', 'reports.view', 'accounting.manage',
        ],
    ];

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function provisionOwner(Tenant $tenant, string $name, string $email, string $password): User
    {
        return $this->tenantContext->run($tenant, function () use ($name, $email, $password): User {
            return DB::transaction(function () use ($name, $email, $password): User {
                $this->ensurePermissions();
                $roles = $this->ensureStandardRoles();

                $user = User::query()->firstOrCreate(
                    ['email' => Str::lower($email)],
                    [
                        'name' => $name,
                        'password' => $password,
                        'is_active' => true,
                    ],
                );

                $user->roles()->sync([$roles['owner']->id]);

                return $user;
            });
        });
    }

    public function ensureForTenant(Tenant $tenant): array
    {
        return $this->tenantContext->run($tenant, function (): array {
            return DB::transaction(function (): array {
                $this->ensurePermissions();

                return $this->ensureStandardRoles();
            });
        });
    }

    public function ensurePermissions(): void
    {
        foreach (self::PERMISSIONS as $code => $name) {
            Permission::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    private function ensureStandardRoles(): array
    {
        $allPermissionIds = Permission::query()->pluck('id');
        $permissionsByCode = Permission::query()->pluck('id', 'code');
        $roles = [];

        foreach (self::ROLE_PERMISSIONS as $code => $permissionCodes) {
            $role = Role::query()->firstOrCreate(
                ['code' => $code],
                ['name' => Str::headline($code), 'is_system' => true],
            );

            $ids = $permissionCodes === '*'
                ? $allPermissionIds
                : collect($permissionCodes)->map(fn (string $permission) => $permissionsByCode[$permission]);

            $role->permissions()->sync($ids);
            $roles[$code] = $role;
        }

        return $roles;
    }
}