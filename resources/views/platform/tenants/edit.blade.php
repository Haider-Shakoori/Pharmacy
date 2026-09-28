@extends('layouts.platform')

@section('title', 'Edit '.$tenant->name.' — Platform Admin')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Tenant management</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Edit {{ $tenant->name }}</h2>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('platform.subscriptions.edit', $tenant) }}" class="rounded-xl border border-teal-700 px-3 py-2 text-xs font-bold text-teal-700">
                Manage subscription
            </a>
            <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold">{{ $tenant->status->value }}</span>
        </div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        @include('platform.tenants._form')
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
            <div>
                <p class="text-sm font-semibold text-teal-700">Provisioning</p>
                <h3 class="mt-1 text-lg font-bold">{{ str_replace('_', ' ', $tenant->provisioning_status) }}</h3>
                @if ($tenant->provisioning_error)
                    <p class="mt-2 text-sm text-red-700">{{ $tenant->provisioning_error }}</p>
                @endif
            </div>
            @if (in_array($tenant->provisioning_status, ['failed', 'awaiting_domain_tls', 'provisioning'], true))
                <form method="POST" action="{{ route('platform.tenants.retry-provisioning', $tenant) }}">
                    @csrf
                    <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold text-white">Retry provisioning</button>
                </form>
            @endif
        </div>

        <div class="mt-4 divide-y divide-slate-100">
            @forelse ($tenant->provisioningEvents->take(10) as $event)
                <div class="grid gap-1 py-3 text-sm sm:grid-cols-[160px_120px_1fr]">
                    <span class="font-medium">{{ str_replace('_', ' ', $event->step) }}</span>
                    <span class="text-slate-500">{{ $event->status }}</span>
                    <span class="text-slate-600">{{ $event->message ?: '—' }}</span>
                </div>
            @empty
                <p class="py-3 text-sm text-slate-500">No provisioning events recorded yet.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
