@extends('layouts.platform')

@section('title', 'License Details — Platform Admin')

@section('content')
@php
    $activeWindows = $license?->activations?->where('platform', 'windows')->whereNull('revoked_at')->count() ?? 0;
@endphp
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <a href="{{ \App\Support\PlatformRoute::url('licenses.index') }}" class="text-sm font-semibold text-teal-700">← Licenses</a>
            <h2 class="mt-2 text-3xl font-bold tracking-tight">{{ $subscription->business->pharmacy_name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $subscription->business->slug }} · {{ $subscription->plan->name }}</p>
        </div>
        @if($license?->status?->value === 'active')
            <form method="POST" action="{{ \App\Support\PlatformRoute::url('licenses.windows-key', $subscription) }}">
                @csrf
                <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Issue one-time Windows key</button>
            </form>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">License</p>
            <p class="mt-2 text-xl font-bold">{{ ucfirst($license?->status?->value ?? 'missing') }}</p>
            <p class="mt-1 text-xs text-slate-500">Version {{ $license?->version ?? '—' }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Windows PCs</p>
            <p class="mt-2 text-xl font-bold">{{ $activeWindows }} / {{ $subscription->plan->max_windows_devices ?? '∞' }}</p>
            <p class="mt-1 text-xs text-slate-500">Active licensed devices</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Cloud sync</p>
            <p class="mt-2 text-xl font-bold {{ $syncEnabled ? 'text-emerald-700' : 'text-slate-600' }}">{{ $syncEnabled ? 'ON' : 'OFF' }}</p>
            <p class="mt-1 text-xs text-slate-500">Managed by platform</p>
        </article>
        <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Activation policy</p>
            <p class="mt-2 text-sm font-bold text-amber-950">One-time Windows keys</p>
            <p class="mt-1 text-xs leading-5 text-amber-800">After first activation the key cannot be reused. PC replacement requires support.</p>
        </article>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="font-bold">Devices & current sessions</h3>
            <p class="mt-1 text-xs text-slate-500">Online means the device has contacted the platform within the last 2 minutes.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">Device</th>
                        <th class="px-4 py-3 text-start">App / OS</th>
                        <th class="px-4 py-3 text-start">Last seen</th>
                        <th class="px-4 py-3 text-start">Signed-in user</th>
                        <th class="px-4 py-3 text-start">Last IP</th>
                        <th class="px-4 py-3 text-end">Support actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($license?->activations ?? [] as $activation)
                    @php
                        $session = $activation->desktopSessions->firstWhere('revoked_at', null);
                        $online = $activation->revoked_at === null && $activation->last_seen_at?->greaterThan(now()->subMinutes(2));
                    @endphp
                    <tr class="{{ $activation->revoked_at ? 'bg-slate-50 opacity-70' : '' }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $activation->device_name ?: 'Unnamed device' }}</p>
                            <p class="mt-1 font-mono text-[11px] text-slate-500">{{ $activation->device_id }}</p>
                            <div class="mt-2 flex gap-2">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $online ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $online ? 'ONLINE' : 'OFFLINE' }}</span>
                                @if($activation->revoked_at)<span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">REVOKED</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ ucfirst($activation->platform) }} · {{ $activation->app_version ?: 'unknown app' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $activation->device_model ?: 'Unknown model' }} · {{ $activation->os_version ?: 'Unknown OS' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ $activation->last_seen_at?->diffForHumans() ?? 'Never' }}</p>
                            <p class="mt-1 text-xs text-slate-500">Activated {{ $activation->activated_at?->diffForHumans() }}</p>
                        </td>
                        <td class="px-4 py-3">
                            @if($session)
                                <p class="font-semibold">{{ $session->user_name }}</p>
                                <p class="text-xs text-slate-500">{{ $session->user_email }}</p>
                            @else
                                <span class="text-slate-400">No active tracked session</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $session?->last_ip ?: $session?->login_ip ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if($session)
                                    <form method="POST" action="{{ \App\Support\PlatformRoute::url('licenses.sessions.sign-out', [$subscription, $session]) }}">
                                        @csrf
                                        <button class="rounded-lg border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-800">Force sign out</button>
                                    </form>
                                @endif
                                @if(!$activation->revoked_at)
                                    <form method="POST" action="{{ \App\Support\PlatformRoute::url('licenses.devices.revoke', [$subscription, $activation]) }}">
                                        @csrf
                                        <button class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700">Revoke device</button>
                                    </form>
                                    @if($activation->platform === 'windows')
                                        <form method="POST" action="{{ \App\Support\PlatformRoute::url('licenses.devices.replace', [$subscription, $activation]) }}">
                                            @csrf
                                            <button class="rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-bold text-white">Replace PC + new key</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No device has activated this license yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-bold">One-time Windows keys</h3>
                <p class="mt-1 text-xs text-slate-500">Plaintext is shown once only. The platform stores only a hash and safe hint.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($license?->activationCodes ?? [] as $code)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p class="font-mono text-xs font-semibold">{{ $code->key_hint }}</p>
                            <p class="mt-1 text-xs text-slate-500">Issued {{ $code->generated_at?->diffForHumans() }}{{ $code->issuedBy ? ' by '.$code->issuedBy->name : '' }}</p>
                        </div>
                        @if($code->consumed_at)
                            <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700">USED</span>
                        @elseif($code->revoked_at)
                            <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700">REVOKED</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">READY</span>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No one-time Windows keys have been issued yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-bold">Support audit trail</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($license?->supportActions ?? [] as $action)
                    <div class="px-5 py-4">
                        <p class="text-sm font-semibold">{{ str_replace('_', ' ', ucfirst($action->action)) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $action->platformAdmin?->name ?? 'System' }} · {{ $action->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No support actions recorded yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
