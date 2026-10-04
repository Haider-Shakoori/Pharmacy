@extends('layouts.platform')

@section('title', 'License Devices — Platform Admin')

@section('content')
@php($routePrefix = request()->routeIs('legacy.platform.*') ? 'legacy.platform.' : 'platform.')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route($routePrefix.'licenses.index') }}" class="text-sm font-semibold text-teal-700">← Back to licenses</a>
            <h2 class="mt-2 text-3xl font-bold tracking-tight">{{ $subscription->business->pharmacy_name }}</h2>
            <p class="mt-1 text-sm text-slate-500">License activations, signed-in staff sessions, and support recovery actions.</p>
        </div>
        @if($license)
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">License</p>
                <p class="mt-1 font-mono text-sm font-bold">{{ $license->key_hint }}</p>
                <p class="mt-1 text-xs text-slate-500">Version {{ $license->version }} · {{ $license->status->value }}</p>
            </div>
        @endif
    </div>

    @if(!$license)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900">This subscription does not have a license yet.</div>
    @else
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Windows key</p>
                <p class="mt-2 text-lg font-bold {{ $license->windows_consumed_at ? 'text-amber-700' : 'text-emerald-700' }}">{{ $license->windows_consumed_at ? 'Consumed' : 'Unused' }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $license->windows_consumed_at ? 'This key cannot activate Windows again.' : 'Ready for one Windows activation.' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Activations</p>
                <p class="mt-2 text-2xl font-bold">{{ $license->activations->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $license->activations->whereNull('revoked_at')->count() }} active</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recovery rule</p>
                <p class="mt-2 text-sm font-bold text-slate-900">Support only</p>
                <p class="mt-1 text-xs text-slate-500">A PC reset/reinstall requires support to issue a brand-new one-time key.</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-lg font-bold">Activated devices & sessions</h3>
                <p class="mt-1 text-xs text-slate-500">“Online” means the device contacted the platform within the last five minutes.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-start">Device</th>
                            <th class="px-4 py-3 text-start">Signed-in staff</th>
                            <th class="px-4 py-3 text-start">Connection</th>
                            <th class="px-4 py-3 text-start">Last seen</th>
                            <th class="px-4 py-3 text-start">Status</th>
                            <th class="px-4 py-3 text-end">Support actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($license->activations as $activation)
                            @php($isOnline = !$activation->revoked_at && $activation->last_seen_at && $activation->last_seen_at->gte(now()->subMinutes(5)))
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <p class="font-semibold">{{ $activation->device_name ?: 'Unnamed device' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ ucfirst($activation->platform) }} · {{ $activation->device_model ?: 'Unknown model' }}</p>
                                    <p class="mt-1 font-mono text-[11px] text-slate-400">{{ $activation->device_id }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $activation->os_version ?: 'OS unknown' }} · App {{ $activation->app_version ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if($activation->current_user_email && !$activation->session_signed_out_at)
                                        <p class="font-semibold">{{ $activation->current_user_name ?: 'Staff user' }}</p>
                                        <p class="text-xs text-slate-500">{{ $activation->current_user_email }}</p>
                                        <p class="mt-1 text-xs text-slate-400">Since {{ $activation->session_started_at?->diffForHumans() ?? '—' }}</p>
                                    @else
                                        <span class="text-xs text-slate-400">No active staff session</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-mono text-xs">{{ $activation->last_ip_address ?: 'IP unavailable' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $activation->build_number ? 'Build '.$activation->build_number : '' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p>{{ $activation->last_seen_at?->diffForHumans() ?? 'Never' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $activation->last_seen_at?->format('Y-m-d H:i:s') }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if($activation->revoked_at)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-700">Revoked</span>
                                    @elseif($isOnline)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">Online</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">Offline</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="ml-auto max-w-xs space-y-3">
                                        @if(!$activation->revoked_at && $activation->platform === 'windows')
                                            <form method="POST" action="{{ route($routePrefix.'licenses.activations.force-sign-out', [$subscription, $activation]) }}" class="space-y-2">
                                                @csrf
                                                <input name="reason" required minlength="5" maxlength="1000" placeholder="Reason for force sign-out" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                                <button class="w-full rounded-lg border border-amber-300 px-3 py-2 text-xs font-bold text-amber-800">Force sign out</button>
                                            </form>
                                            <form method="POST" action="{{ route($routePrefix.'licenses.activations.reset-device', [$subscription, $activation]) }}" class="space-y-2" onsubmit="return confirm('Revoke this activation and issue a brand-new one-time key? The old key will remain unusable.');">
                                                @csrf
                                                <input name="reason" required minlength="5" maxlength="1000" placeholder="Support reset reason" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                                                <button class="w-full rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white">Reset device & issue new key</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-400">No support action available</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No devices have activated this license yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-lg font-bold">Support audit log</h3>
                <p class="mt-1 text-xs text-slate-500">Device resets, key reissues, force sign-outs, and revocations are recorded here.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($supportActions as $action)
                    <div class="grid gap-2 px-5 py-4 text-sm md:grid-cols-4">
                        <div><p class="font-semibold">{{ str_replace('_', ' ', ucfirst($action->action)) }}</p><p class="text-xs text-slate-500">{{ $action->created_at?->format('Y-m-d H:i:s') }}</p></div>
                        <div><p class="text-xs text-slate-500">Support operator</p><p class="font-semibold">{{ $supportAdmins[$action->platform_admin_id] ?? ($action->platform_admin_id ?: 'System') }}</p></div>
                        <div class="md:col-span-2"><p class="text-xs text-slate-500">Reason</p><p>{{ $action->reason ?: '—' }}</p></div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-sm text-slate-500">No support actions have been recorded yet.</div>
                @endforelse
            </div>
        </div>
    @endif
</div>
@endsection
