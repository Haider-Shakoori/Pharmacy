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
        <a href="{{ route('pharmacy.dashboard') }}" class="block rounded-lg bg-teal-50 px-3 py-2.5 text-sm font-semibold text-teal-800">
            {{ __('pharmacy.nav.dashboard') }}
        </a>

        @if (auth()->user()->hasPermission('users.manage'))
            <a href="{{ route('pharmacy.users.index') }}"
               class="block rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('pharmacy.users.*') ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-50' }}">
                {{ __('pharmacy.nav.users') }}
            </a>
        @endif

        @foreach ([
            ['label' => __('pharmacy.nav.medicines'), 'batch' => 9],
            ['label' => __('pharmacy.nav.inventory'), 'batch' => 11],
            ['label' => __('pharmacy.nav.suppliers'), 'batch' => 10],
            ['label' => __('pharmacy.nav.pos'), 'batch' => 12],
            ['label' => 'Daily Closing', 'batch' => 13],
            ['label' => __('pharmacy.nav.reports'), 'batch' => 14],
            ['label' => __('pharmacy.nav.accounting'), 'batch' => 15],
            ['label' => __('pharmacy.nav.devices'), 'batch' => 18],
            ['label' => __('pharmacy.nav.settings'), 'batch' => 8],
        ] as $item)
            <div class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm text-slate-500">
                <span>{{ $item['label'] }}</span>
                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px]">Batch {{ $item['batch'] }}</span>
            </div>
        @endforeach
    </nav>
</div>
