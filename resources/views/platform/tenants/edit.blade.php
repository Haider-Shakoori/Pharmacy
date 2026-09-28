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

    @php
        $business = $tenant->business;
        $subscription = $business?->subscription;
        $trialUsed = $business?->trial_used_at !== null;
        $trialEligible = $tenant->provisioning_status === 'application_ready' && ! $trialUsed && $subscription === null;
    @endphp

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">Subscription actions</p>
                <h3 class="mt-1 text-lg font-bold">Hosted trial</h3>
                <p class="mt-1 text-sm text-slate-500">
                    @if ($trialUsed)
                        This pharmacy has already used its one-time hosted trial.
                    @elseif ($subscription)
                        A {{ $subscription->status->value }} subscription already exists. Manage that subscription instead of starting a trial.
                    @elseif ($tenant->provisioning_status !== 'application_ready')
                        The 7-day trial becomes available only after provisioning is fully ready.
                    @else
                        Eligible for one 7-day hosted trial. The trial starts only when you press the button below.
                    @endif
                </p>
                @if ($subscription?->status?->value === 'trial')
                    <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-900">
                        Trial active until {{ $subscription->trial_ends_at?->format('Y-m-d H:i') }}.
                    </div>
                @endif
                @error('trial')
                    <p class="mt-3 text-sm font-semibold text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <form method="POST" action="{{ route('platform.tenants.trial.start', $tenant) }}">
                @csrf
                <button @disabled(! $trialEligible)
                        class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-slate-950 disabled:cursor-not-allowed disabled:opacity-40">
                    Start 7-day trial
                </button>
            </form>
        </div>
        @if ($business?->trial_used_at)
            <p class="mt-4 text-xs text-slate-500">First trial started {{ $business->trial_used_at->format('Y-m-d H:i') }}.</p>
        @endif
    </section>

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
