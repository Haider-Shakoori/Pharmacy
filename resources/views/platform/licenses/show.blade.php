@extends('layouts.platform')

@section('title', 'License devices — Platform Admin')

@section('content')
@php
    $platformRoutePrefix = request()->routeIs('legacy.platform.*') ? 'legacy.platform.' : 'platform.';
    $license = $subscription->license;
    $activations = $license?->activations ?? collect();
    $windows = $activations->where('platform', 'windows');
    $activeWindows = $windows->whereNull('revoked_at');
    $supportActions = $license?->supportActions ?? collect();
@endphp

<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route($platformRoutePrefix.'licenses.index') }}" class="text-sm font-semibold text-teal-700">← Back to licenses</a>
            <h2 class="mt-2 text-3xl font-bold tracking-tight">{{ $subscription->business->pharmacy_name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $subscription->business->slug }} · {{ $subscription->plan->name }}</p>
        </div>
        @if ($license)
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Current key</p>
                <p class="mt-1 font-mono font-semibold">{{ $license->key_hint }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $license->status->value === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                        {{ strtoupper($license->status->value) }}
                    </span>
                    @if ($license->activation_key_consumed_at)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">ONE-TIME KEY CONSUMED</span>
                    @else
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-bold text-sky-800">ONE-TIME KEY READY</span>
                    @endif
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">Generation {{ $license->activation_key_generation }}</span>
                </div>
            </div>
        @endif
    </div>

    @if (!$license)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
            No license exists for this subscription yet.
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Active Windows PCs</p>
                <p class="mt-2 text-3xl font-black">{{ $activeWindows->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">Plan limit: {{ $subscription->plan->max_windows_devices ?? 'Unlimited' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Known devices</p>
                <p class="mt-2 text-3xl font-black">{{ $windows->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">Includes released/revoked PCs</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Current staff sessions</p>
                <p class="mt-2 text-3xl font-black">{{ $activeWindows->whereNotNull('current_user_email')->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">Server-observed sessions</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Key state</p>
                <p class="mt-2 text-lg font-black">{{ $license->activation_key_consumed_at ? 'Consumed' : 'Ready' }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $license->activation_key_consumed_at?->diffForHumans() ?? 'Awaiting first Windows activation' }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <h3 class="text-lg font-bold text-slate-900">Issue next one-time Windows activation key</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Use this only when support authorizes another PC. The plaintext key is shown once, is never stored, and becomes unusable immediately after one successful Windows activation.
                    </p>
                </div>
                <form method="POST" action="{{ route($platformRoutePrefix.'licenses.issue-next-key', $subscription) }}" class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row lg:max-w-xl">
                    @csrf
                    <input name="reason" maxlength="1000" placeholder="Support reason / ticket (optional)" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    <button onclick="return confirm('Issue a new one-time Windows activation key? The previous unused key, if any, will stop working.')" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Issue one-time key</button>
                </form>
            </div>
        </div>

        <section class="space-y-3">
            <div>
                <h3 class="text-xl font-bold">Windows activations & sessions</h3>
                <p class="mt-1 text-sm text-slate-500">See which PC is bound, who is signed in, where the request last came from, and when the device was last active.</p>
            </div>

            @forelse ($windows as $activation)
                @php
                    $isRevoked = $activation->revoked_at !== null;
                    $isOnline = !$isRevoked
                        && $activation->session_last_seen_at
                        && $activation->session_last_seen_at->greaterThan(now()->subMinutes(2));
                @endphp
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="font-bold">{{ $activation->device_name ?: 'Unnamed Windows PC' }}</h4>
                                @if ($isRevoked)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-800">RELEASED</span>
                                @elseif ($isOnline)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">ONLINE</span>
                                @else
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-bold text-slate-700">OFFLINE</span>
                                @endif
                            </div>
                            <p class="mt-1 break-all font-mono text-[11px] text-slate-500">{{ $activation->device_id }}</p>
                        </div>
                        <div class="text-xs text-slate-500">
                            Activated {{ $activation->activated_at?->diffForHumans() ?? '—' }}
                        </div>
                    </div>

                    <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Signed-in user</p>
                            <p class="mt-1 font-semibold">{{ $activation->current_user_name ?? 'No active user' }}</p>
                            <p class="text-xs text-slate-500">{{ $activation->current_user_email ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Last IP / where</p>
                            <p class="mt-1 font-mono text-sm font-semibold">{{ $activation->last_ip_address ?? 'Not recorded yet' }}</p>
                            <p class="text-xs text-slate-500">Last server-observed address</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">App & Windows</p>
                            <p class="mt-1 font-semibold">Darmaltoon {{ $activation->app_version ?? '—' }}</p>
                            <p class="text-xs text-slate-500">{{ $activation->os_version ?? 'Unknown OS' }} @if($activation->build_number) · {{ $activation->build_number }} @endif</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Last activity</p>
                            <p class="mt-1 font-semibold">{{ $activation->last_seen_at?->diffForHumans() ?? 'Never' }}</p>
                            <p class="text-xs text-slate-500">Session: {{ $activation->session_last_seen_at?->diffForHumans() ?? 'No session activity' }}</p>
                        </div>
                    </div>

                    @if (!$isRevoked)
                        <div class="grid gap-3 border-t border-slate-100 bg-slate-50 p-4 lg:grid-cols-2">
                            <form method="POST" action="{{ route($platformRoutePrefix.'licenses.force-sign-out', $activation) }}" class="flex gap-2">
                                @csrf
                                <input name="reason" maxlength="1000" placeholder="Reason (optional)" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                <button onclick="return confirm('Force this staff session to sign out? The PC will remain activated.')" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">Force sign out</button>
                            </form>
                            <form method="POST" action="{{ route($platformRoutePrefix.'licenses.release-reassign', $activation) }}" class="flex gap-2">
                                @csrf
                                <input name="reason" required maxlength="1000" placeholder="Required: PC failure / replacement reason" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
                                <button onclick="return confirm('Release this PC and issue a brand-new one-time replacement key? The old activation will stop working.')" class="rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white">Release & reassign</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                    No Windows PC has been activated yet.
                </div>
            @endforelse
        </section>

        <section class="space-y-3">
            <div>
                <h3 class="text-xl font-bold">Support audit trail</h3>
                <p class="mt-1 text-sm text-slate-500">Every support-issued key, forced sign-out, and PC reassignment is recorded.</p>
            </div>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-start">Time</th>
                                <th class="px-4 py-3 text-start">Action</th>
                                <th class="px-4 py-3 text-start">Support admin</th>
                                <th class="px-4 py-3 text-start">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($supportActions->sortByDesc('created_at') as $action)
                                <tr>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ $action->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ str($action->action)->replace('_', ' ')->title() }}</td>
                                    <td class="px-4 py-3">{{ $action->platformAdmin?->email ?? 'System' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $action->reason ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No support actions have been recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
</div>
@endsection
