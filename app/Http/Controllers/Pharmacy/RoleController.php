<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreRoleRequest;
use App\Http\Requests\Pharmacy\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('pharmacy.roles.index', [
            'roles' => Role::query()
                ->withCount(['users', 'permissions'])
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('pharmacy.roles.create', [
            'permissions' => Permission::query()->orderBy('code')->get(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $permissionIds = $validated['permission_ids'];
        unset($validated['permission_ids']);

        $role = Role::query()->create($validated + ['is_system' => false]);
        $role->permissions()->sync($permissionIds);
        $role->touch();

        return redirect()
            ->route('pharmacy.roles.edit', $role)
            ->with('success', 'Custom role created.');
    }

    public function edit(Role $role): View
    {
        return view('pharmacy.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('code')->get(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->code === 'owner') {
            return back()->withErrors(['permissions' => 'The Owner role always retains all permissions.']);
        }

        $role->permissions()->sync($request->validated('permission_ids'));
        $role->touch();

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereKey($role->id))
            ->update(['updated_at' => now()]);

        return back()->with('success', 'Role permissions updated.');
    }
}
