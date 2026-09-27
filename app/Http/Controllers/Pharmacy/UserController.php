<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreUserRequest;
use App\Http\Requests\Pharmacy\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('pharmacy.users.index', [
            'users' => User::query()
                ->with('roles:id,name')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(config('pharmacy.performance.default_page_size'))
                ->withQueryString(),
            'search' => $search,
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
        $data = $request->validated();
        $roles = Role::query()->whereKey($data['roles'])->pluck('id');

        abort_if($roles->count() !== count(array_unique($data['roles'])), 422, 'One or more roles are invalid.');

        $user = User::query()->create(Arr::except($data, 'roles'));
        $user->roles()->sync($roles);

        return redirect()->route('pharmacy.users.edit', $user)
            ->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('pharmacy.users.edit', [
            'user' => $user->load('roles:id,name'),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()) && ! $request->boolean('is_active'), 422, 'You cannot deactivate your own account.');

        $data = $request->validated();
        $roles = Role::query()->whereKey($data['roles'])->pluck('id');

        abort_if($roles->count() !== count(array_unique($data['roles'])), 422, 'One or more roles are invalid.');

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update(Arr::except($data, 'roles'));
        $user->roles()->sync($roles);

        return back()->with('success', 'User updated.');
    }
}
