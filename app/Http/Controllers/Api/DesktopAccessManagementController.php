<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Desktop\DesktopAccessService;
use App\Services\Sync\SyncAccessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DesktopAccessManagementController extends Controller
{
    public function index(Request $request, DesktopAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        $this->requireAnyPermission($context, ['users.manage', 'roles.manage']);

        return $context->tenant->run(function (): JsonResponse {
            return response()->json([
                'data' => $this->snapshot(),
                'server_time' => now()->toIso8601String(),
            ]);
        });
    }

    public function storeUser(Request $request, DesktopAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        $this->requirePermission($context, 'users.manage');

        return $context->tenant->run(function () use ($request): JsonResponse {
            $data = $request->validate($this->userRules());

            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
                'is_active' => (bool) $data['is_active'],
            ]);
            $user->roles()->sync($data['role_ids']);
            $user->touch();

            return response()->json([
                'data' => $this->snapshot(),
                'message' => 'Pharmacy user created.',
                'server_time' => now()->toIso8601String(),
            ], 201);
        });
    }

    public function updateUser(
        Request $request,
        int $userId,
        DesktopAccessService $access,
    ): JsonResponse {
        $context = $access->authenticate($request);
        $this->requirePermission($context, 'users.manage');

        return $context->tenant->run(function () use ($request, $context, $userId): JsonResponse {
            $user = User::query()->findOrFail($userId);
            $data = $request->validate($this->userRules($userId, creating: false));

            if ((int) $context->userId === $userId && ! (bool) $data['is_active']) {
                return response()->json([
                    'message' => 'You cannot deactivate your own account.',
                ], 422);
            }

            $updates = [
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'is_active' => (bool) $data['is_active'],
            ];
            if (! blank($data['password'] ?? null)) {
                $updates['password'] = $data['password'];
            }

            $user->update($updates);
            $user->roles()->sync($data['role_ids']);

            return response()->json([
                'data' => $this->snapshot(),
                'message' => 'Pharmacy user updated.',
                'server_time' => now()->toIso8601String(),
            ]);
        });
    }

    public function storeRole(Request $request, DesktopAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        $this->requirePermission($context, 'roles.manage');

        return $context->tenant->run(function () use ($request): JsonResponse {
            $data = $request->validate($this->roleRules());
            $code = Str::slug((string) $data['code'], '_');

            $role = Role::query()->create([
                'name' => trim($data['name']),
                'code' => $code,
                'is_system' => false,
            ]);
            $role->permissions()->sync($data['permission_ids']);
            $role->touch();

            return response()->json([
                'data' => $this->snapshot(),
                'message' => 'Custom role created.',
                'server_time' => now()->toIso8601String(),
            ], 201);
        });
    }

    public function updateRole(
        Request $request,
        int $roleId,
        DesktopAccessService $access,
    ): JsonResponse {
        $context = $access->authenticate($request);
        $this->requirePermission($context, 'roles.manage');

        return $context->tenant->run(function () use ($request, $roleId): JsonResponse {
            $role = Role::query()->findOrFail($roleId);
            if ($role->code === 'owner') {
                return response()->json([
                    'message' => 'The Owner role always retains all permissions.',
                ], 422);
            }

            $data = $request->validate([
                'permission_ids' => ['required', 'array', 'min:1'],
                'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
            ]);

            $role->permissions()->sync($data['permission_ids']);
            $role->touch();

            User::query()
                ->whereHas('roles', fn ($query) => $query->whereKey($role->id))
                ->update(['updated_at' => now()]);

            return response()->json([
                'data' => $this->snapshot(),
                'message' => 'Role permissions updated.',
                'server_time' => now()->toIso8601String(),
            ]);
        });
    }

    private function snapshot(): array
    {
        $users = User::query()
            ->with('roles:id,name,code')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->roles->map(fn (Role $role): array => [
                    'id' => (int) $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                ])->values()->all(),
            ])->values()->all();

        $roles = Role::query()
            ->with('permissions:id,code,name,description')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'is_system' => (bool) $role->is_system,
                'users_count' => (int) $role->users_count,
                'permissions' => $role->permissions->map(fn (Permission $permission): array => [
                    'id' => (int) $permission->id,
                    'code' => $permission->code,
                    'name' => $permission->name,
                    'description' => $permission->description,
                ])->values()->all(),
            ])->values()->all();

        $permissions = Permission::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'description'])
            ->map(fn (Permission $permission): array => [
                'id' => (int) $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'description' => $permission->description,
            ])->values()->all();

        return compact('users', 'roles', 'permissions');
    }

    private function userRules(?int $userId = null, bool $creating = true): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
        ];
    }

    private function roleRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:80'],
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    private function requirePermission(SyncAccessContext $context, string $permission): void
    {
        abort_unless(in_array($permission, $context->permissions, true), 403);
    }

    private function requireAnyPermission(SyncAccessContext $context, array $permissions): void
    {
        abort_unless(
            collect($permissions)->contains(
                fn (string $permission): bool => in_array($permission, $context->permissions, true),
            ),
            403,
        );
    }
}
