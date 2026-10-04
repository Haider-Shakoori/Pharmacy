@extends('layouts.platform')

@section('title', 'Pharmacies — Platform Admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Tenant management</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Pharmacies</h2>
        </div>
        <a href="{{ \App\Support\PlatformRoute::url('tenants.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add pharmacy</a>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_180px_auto]">
        <input name="search" value="{{ $search }}" placeholder="Search name or slug"
               class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">All statuses</option>
            @foreach (['active', 'suspended', 'archived'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full text-start text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">Pharmacy</th>
                        <th class="px-4 py-3 text-start">Status</th>
                        <th class="px-4 py-3 text-start">Provisioning</th>
                        <th class="px-4 py-3 text-start">Domain</th>
                        <th class="px-4 py-3 text-start">Locale</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tenants as $tenant)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $tenant->name }}</p>
                                <p class="text-xs text-slate-500">{{ $tenant->slug }}</p>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ $tenant->status->value }}</span></td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $tenant->provisioning_status) }}</td>
                            <td class="px-4 py-3 text-xs">{{ $tenant->domains->first()?->domain ?? '—' }}</td>
                            <td class="px-4 py-3">{{ strtoupper($tenant->locale) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ \App\Support\PlatformRoute::url('tenants.edit', $tenant) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold">Edit</a>
                                    @foreach (['active', 'suspended', 'archived'] as $nextStatus)
                                        @if ($tenant->status->value !== $nextStatus)
                                            <form method="POST" action="{{ \App\Support\PlatformRoute::url('tenants.status', [$tenant, $nextStatus]) }}">
                                                @csrf
                                                @method('PUT')
                                                <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold">{{ ucfirst($nextStatus) }}</button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No pharmacies match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tenants->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $tenants->links() }}</div>
        @endif
    </div>
</div>
@endsection
