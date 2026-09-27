@extends('layouts.pharmacy')

@section('title', 'Users — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Access control</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">Users</h1>
        </div>
        <a href="{{ route('pharmacy.users.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add user</a>
    </div>

    <form method="GET" class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <input name="search" value="{{ $search }}" placeholder="Search name or email"
               class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Search</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">User</th>
                        <th class="px-4 py-3 text-start">Roles</th>
                        <th class="px-4 py-3 text-start">Status</th>
                        <th class="px-4 py-3 text-start">Last login</th>
                        <th class="px-4 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $user->roles->pluck('name')->join(', ') }}</td>
                            <td class="px-4 py-3">{{ $user->is_active ? 'Active' : 'Disabled' }}</td>
                            <td class="px-4 py-3">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-4 py-3 text-end"><a class="font-semibold text-teal-700" href="{{ route('pharmacy.users.edit', $user) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $users->links() }}</div>@endif
    </div>
</div>
@endsection
