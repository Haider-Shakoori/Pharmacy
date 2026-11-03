@extends('layouts.platform')

@section('title', 'User logins — Platform Admin')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-teal-700">Pharmacy access</p>
            <h2 class="mt-1 text-2xl font-bold">{{ $tenant->name }} — User logins</h2>
            <p class="mt-1 text-sm text-slate-500">Most recent successful login per user. This is not a complete session history or live online status.</p>
        </div>
        <a href="{{ route('platform.tenants.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Back to pharmacies</a>
    </div>
    @if ($unavailable)
        <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">The pharmacy database is temporarily unavailable.</p>
    @elseif ($tenant->provisioning_status !== 'application_ready')
        <p class="rounded-xl border border-slate-200 p-4 text-sm">Pharmacy provisioning is not complete.</p>
    @else
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Last successful login</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $user['name'] }}</td>
                            <td class="px-4 py-3">{{ $user['email'] }}</td>
                            <td class="px-4 py-3">
                                @if ($user['at'])
                                    {{ \Carbon\CarbonImmutable::parse($user['at'])->timezone($tenant->timezone)->format('d M Y, h:i A') }}
                                    <span class="text-xs text-slate-500">({{ $tenant->timezone }})</span>
                                @else
                                    <span class="text-slate-500">Never logged in</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
