@extends('layouts.pharmacy')

@section('title', 'Users — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div class="flex items-end justify-between gap-3">
        <div><p class="text-sm font-semibold text-teal-700">Access control</p><h1 class="text-3xl font-bold">Users</h1></div>
        <a href="{{ route('pharmacy.users.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add user</a>
    </div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-start">User</th><th class="px-4 py-3 text-start">Roles</th><th class="px-4 py-3 text-start">Status</th><th class="px-4 py-3 text-end">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3"><p class="font-semibold">{{ $user->name }}</p><p class="text-xs text-slate-500">{{ $user->email }}</p></td>
                        <td class="px-4 py-3">{{ $user->roles->pluck('name')->join(', ') }}</td>
                        <td class="px-4 py-3">{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3 text-end"><a href="{{ route('pharmacy.users.edit', $user) }}" class="font-bold text-teal-700">Edit</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $users->links() }}</div>@endif
    </div>
</div>
@endsection
