@extends('layouts.platform')

@section('title', 'License Devices & Sessions — Platform Admin')

@section('content')
@php
    $license = $subscription->license;
    $activations = $license?->activations ?? collect();
    $activeActivations = $activations->whereNull('revoked_at');
    $activeWindows = $activeActivations->where('platform', 'windows');
    $activeAndroid = $activeActivations->where('platform', 'android');
@endphp

<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-sm font-semibold text-teal-700">Activation security</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">{{ $subscription->business->pharmacy_name }}</h2>
            <p class="mt-2 text-sm text-slate-500">
                License activations, current desktop sessions, device metadata, and support-controlled reassignment.
            </p>
        </div>
        <a href="{{ route('platform.licenses.index') }}"
           class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">
            Back to licenses
        </a>
    </div>

    @if (! $license)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
            No license exists for this subscription yet.
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">License</p>
                <p class="mt-2 font-mono text-sm font-bold">{{ $license->key_hint }}</p>
                <p class="mt-2 text-xs text-slate-500">Version {{ $license->version }} · {{ $license->status->value }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Windows key</p>
                @if ($license->windows_activation_key_consumed_at)
                    <p class="mt-2 text-lg font-black text-amber-700">Consumed</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $license->windows_activation_key_consumed_at->diffForHumans() }}</p>
                @else
                    <p class="mt-2 text-lg font-black text-emerald-700">Ready for one activation</p>
                    <p class="mt-1 text-xs text-slate-500">Key version {{ $license->windows_activation_key_version }}</p>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Active Windows</p>
                <p class="mt-2 text-3xl font-black">{{ $activeWindows->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $subscription->plan->max_windows_devices ?? 'Unlimited' }} allowed by plan</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Active Android</p>
                <p class="mt-2 text-3xl font-black">{{ $activeAndroid->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $subscription->plan->max_android_devices ?? 'Unlimited' }} allowed by plan</p>
            </div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-base font-black text-amber-950">Windows activation is one-time and support-controlled</h3>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-amber-900">
                        The first successful Windows activation consumes the key. Reinstalling Windows, replacing the PC, losing local activation data,
                        or attempting another activation requires platform support. Reassigning below revokes existing Windows activations and creates a new one-time key.
                    </p>
                </div>
                <form method="POST" action="{{ route('platform.licenses.windows.reassign', $subscription) }}"
                      onsubmit="return confirm('Revoke all active Windows devices and generate a new one-time activation key?')">
                    @csrf
                    <button class="rounded-xl bg-amber-900 px-4 py-3 text-sm font-black text-white">
                        Reassign Windows device
                    </button>
                </form>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-lg font-black">Activations & sessions</h3>
                <p class="mt-1 text-xs text-slate-500">Device, current staff session, last activity, app/OS version and last network address.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-start">Device</th>
                            <th class="px-4 py-3 text-start">User session</th>
                            <th class="px-4 py-3 text-start">App / OS</th>
                            <th class="px-4 py-3 text-start">Last seen</th>
                            <th class="px-4 py-3 text-start">Network</th>
                            <th class="px-4 py-3 text-start">Status</th>
                            <th class="px-4 py-3 text-end">Support actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($activations as $activation)
                            @php
                                $recent = $activation->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false;
                            @endphp
                            <tr class="{{ $activation->revoked_at ? 'bg-slate-50/70' : '' }}">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-bold">{{ $activation->device_name ?: 'Unnamed device' }}</p>
                                    <p class="mt-1 font-mono text-[11px] text-slate-500">{{ $activation->device_id }}</p>
                                    <p class="mt-1 text-xs font-semibold uppercase text-slate-500">{{ $activation->platform }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Activated {{ $activation->activated_at?->diffForHumans() }}</p>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    @if ($activation->current_user_email)
                                        <p class="font-semibold">{{ $activation->current_user_name ?: 'User' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $activation->current_user_email }}</p>
                                        @if ($activation->session_expires_at)
                                            <p class="mt-1 text-xs text-slate-500">Session expires {{ $activation->session_expires_at->diffForHumans() }}</p>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">No active tracked session</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p>{{ $activation->app_version ?: 'Unknown app version' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $activation->os_version ?: 'Unknown OS' }}</p>
                                    @if ($activation->device_model)
                                        <p class="mt-1 text-xs text-slate-500">{{ $activation->device_model }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold">{{ $activation->last_seen_at?->diffForHumans() ?? 'Never' }}</p>
                                    @if ($activation->last_session_activity_at)
                                        <p class="mt-1 text-xs text-slate-500">Session {{ $activation->last_session_activity_at->diffForHumans() }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-mono text-xs">{{ $activation->last_ip_address ?: '—' }}</p>
                                    <p class="mt-1 max-w-xs truncate text-[11px] text-slate-400" title="{{ $activation->last_user_agent }}">
                                        {{ $activation->last_user_agent ?: 'No user agent recorded' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    @if ($activation->revoked_at)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-700">Revoked</span>
                                        <p class="mt-1 text-xs text-slate-500">{{ $activation->revoked_at->diffForHumans() }}</p>
                                    @elseif ($recent)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">Online / recent</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">Offline</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    @if (! $activation->revoked_at)
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @if ($activation->current_user_email)
                                                <form method="POST" action="{{ route('platform.licenses.activations.force-sign-out', [$subscription, $activation]) }}">
                                                    @csrf
                                                    <button class="rounded-lg border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-800">
                                                        Force sign out
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('platform.licenses.activations.revoke', [$subscription, $activation]) }}"
                                                  onsubmit="return confirm('Revoke this device activation?')">
                                                @csrf
                                                <button class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700">
                                                    Revoke device
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">No actions</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500">No device activations have been recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-lg font-black">Support audit trail</h3>
                <p class="mt-1 text-xs text-slate-500">Recent support-controlled license and session actions.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($auditActions as $action)
                    <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold">{{ str($action->action)->replace('_', ' ')->title() }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $action->platformAdmin?->email ?? 'System / unknown admin' }}
                                @if ($action->activation)
                                    · {{ $action->activation->device_name ?: $action->activation->device_id }}
                                @endif
                            </p>
                        </div>
                        <p class="text-xs text-slate-500">{{ $action->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <div class="px-5 py-8 text-sm text-slate-500">No support actions recorded yet.</div>
                @endforelse
            </div>
        </div>
    @endif
</div>
@endsection
