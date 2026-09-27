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

    @php
        $nav = [
            ['key' => 'dashboard', 'batch' => 1, 'active' => true],
            ['key' => 'medicines', 'batch' => 9],
            ['key' => 'inventory', 'batch' => 11],
            ['key' => 'suppliers', 'batch' => 10],
            ['key' => 'pos', 'batch' => 12],
            ['key' => 'returns', 'batch' => 13],
            ['key' => 'reports', 'batch' => 14],
            ['key' => 'accounting', 'batch' => 15],
            ['key' => 'users', 'batch' => 7],
            ['key' => 'devices', 'batch' => 18],
            ['key' => 'settings', 'batch' => 8],
        ];
    @endphp

    <nav class="flex-1 space-y-1 px-4 py-5">
        @foreach ($nav as $item)
            <a href="{{ ($item['active'] ?? false) ? route('pharmacy.dashboard') : '#' }}"
               class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium {{ ($item['active'] ?? false) ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-50' }}">
                <span>{{ __('pharmacy.nav.'.$item['key']) }}</span>
                @unless ($item['active'] ?? false)
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500">{{ __('pharmacy.coming_batch', ['batch' => $item['batch']]) }}</span>
                @endunless
            </a>
        @endforeach
    </nav>

    <div class="border-t border-slate-100 p-4">
        <div class="rounded-xl bg-slate-900 p-4 text-white">
            <p class="text-xs font-semibold uppercase tracking-wider text-teal-300">{{ __('pharmacy.low_bandwidth') }}</p>
            <p class="mt-1 text-xs leading-5 text-slate-300">Server-rendered UI, compact assets, server-side data operations and delta-sync by design.</p>
        </div>
    </div>
</div>
