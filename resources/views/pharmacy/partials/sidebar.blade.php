<div class="flex h-full min-h-screen flex-col">
    <div class="border-b border-slate-100 px-6 py-6">
        <div class="flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-xl bg-teal-700 text-lg font-black text-white">Rx</div>
            <div>
                <p class="font-bold text-slate-900">{{ __('pharmacy.product') }}</p>
                <p class="text-xs text-slate-500">Afghanistan • AFN</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 space-y-1 px-4 py-5">
        @if (auth()->user()->hasPermission('dashboard.view'))
            <a href="{{ route('pharmacy.dashboard') }}" class="block rounded-lg bg-teal-50 px-3 py-2.5 text-sm font-semibold text-teal-800">{{ __('pharmacy.nav.dashboard') }}</a>
        @endif
        @if (auth()->user()->hasPermission('users.manage'))
            <a href="{{ route('pharmacy.users.index') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">{{ __('pharmacy.nav.users') }}</a>
        @endif
        @if (auth()->user()->hasPermission('roles.manage'))
            <a href="{{ route('pharmacy.roles.index') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Roles & Permissions</a>
        @endif

        <div class="my-3 border-t border-slate-100"></div>
        <span class="block rounded-lg px-3 py-2.5 text-sm text-slate-400">{{ __('pharmacy.nav.medicines') }} · Batch 9</span>
        <span class="block rounded-lg px-3 py-2.5 text-sm text-slate-400">{{ __('pharmacy.nav.inventory') }} · Batch 11</span>
        <span class="block rounded-lg px-3 py-2.5 text-sm text-slate-400">{{ __('pharmacy.nav.pos') }} · Batch 12</span>
        <span class="block rounded-lg px-3 py-2.5 text-sm text-slate-400">Daily Closing · Batch 13</span>
    </nav>

    <div class="border-t border-slate-100 p-4">
        <div class="rounded-xl bg-slate-900 p-4 text-white">
            <p class="text-xs font-semibold uppercase tracking-wider text-teal-300">{{ __('pharmacy.low_bandwidth') }}</p>
            <p class="mt-1 text-xs leading-5 text-slate-300">Server-rendered UI, compact assets and offline-first Android synchronization.</p>
        </div>
    </div>
</div>
