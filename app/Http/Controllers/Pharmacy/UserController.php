<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreUserRequest;
use App\Http\Requests\Pharmacy\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('pharmacy.users.index', [
            'users' => User::query()
                ->with('roles')
                ->orderBy('name')
                ->paginate(config('pharmacy.performance.default_page_size')),
        ]);
    }

    public function create(): View
    {
        return view('pharmacy.users.create', [
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roleIds = $validated['role_ids'];
        unset($validated['role_ids']);

        $validated['email'] = Str::lower($validated['email']);

        $user = User::query()->create($validated);
        $user->roles()->sync($roleIds);
        $user->touch();

        return redirect()
            ->route('pharmacy.users.edit', $user)
            ->with('success', 'Pharmacy user created.');
    }

    public function edit(User $user): View
    {
        return view('pharmacy.users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $roleIds = $validated['role_ids'];
        unset($validated['role_ids']);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        if ($request->user()->is($user) && ! $validated['is_active']) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        $validated['email'] = Str::lower($validated['email']);

        $user->update($validated);
        $user->roles()->sync($roleIds);
        $user->touch();

        return back()->with('success', 'Pharmacy user updated.');
    }
}
