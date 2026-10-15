<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), config('pharmacy.rtl_locales'), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B82F6">
    <title>@yield('title', __('pharmacy.product'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class', 'bg-slate-50 text-slate-900 antialiased')">
@hasSection('fullscreen')
    @yield('content')
@else
<div class="dt-shell lg:flex" x-data="{ mobileNav: false, compact: false, init() { try { this.compact = localStorage.getItem('darmaltoon.sidebar.compact') === '1' } catch(e) {} }, toggleSidebar() { this.compact = !this.compact; try { localStorage.setItem('darmaltoon.sidebar.compact', this.compact ? '1' : '0') } catch(e) {} } }">
    <aside id="desktopPharmacySidebar" class="dt-sidebar hidden shrink-0 border-e lg:block" :class="{ 'dt-sidebar-compact': compact }">
        @include('pharmacy.partials.sidebar')
    </aside>

    <div class="min-w-0 flex-1">
        <header class="dt-topbar sticky top-0 z-20 border-b backdrop-blur">
            <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button type="button" class="dt-action hidden lg:inline-flex" @click="toggleSidebar()" :aria-expanded="(!compact).toString()" aria-controls="desktopPharmacySidebar" aria-label="Toggle sidebar" title="Toggle sidebar">☰</button>
                    <button class="rounded-lg border border-slate-200 p-2 lg:hidden" @click="mobileNav = !mobileNav" aria-label="Menu" :aria-expanded="mobileNav.toString()" aria-controls="mobilePharmacyNav">
                        <span class="block h-0.5 w-5 bg-slate-700"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-slate-700"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-slate-700"></span>
                    </button>
                    <div>
                        <p class="text-sm font-semibold">{{ app(\App\Support\Tenancy\TenantContext::class)->tenant()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->name }}</p>
                    </div>
                </div>

                <div class="dt-locale flex items-center gap-2 text-xs font-semibold">
                    @foreach (['en' => 'EN', 'fa' => 'دری', 'ps' => 'پښتو'] as $locale => $label)
                        <a href="{{ route('pharmacy.locale.switch', $locale) }}"
                           class="rounded-md px-2.5 py-1.5 {{ app()->getLocale() === $locale ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $label }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('pharmacy.logout') }}">
                        @csrf
                        <button class="rounded-md bg-slate-900 px-2.5 py-1.5 text-white">{{ __('pharmacy.actions.sign_out') !== 'pharmacy.actions.sign_out' ? __('pharmacy.actions.sign_out') : 'Sign out' }}</button>
                    </form>
                </div>
            </div>
            <div id="mobilePharmacyNav" class="dt-mobile-panel border-t border-slate-100 bg-white lg:hidden" x-show="mobileNav" x-cloak>
                @include('pharmacy.partials.mobile-nav')
            </div>
        </header>

        <main class="dt-main px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @php
                $layoutTenant = app(\App\Support\Tenancy\TenantContext::class)->tenant();
                $layoutSubscription = $layoutTenant->subscription()->first();
            @endphp
            @if ($layoutSubscription?->status?->value === 'trial')
                <div class="mb-5 flex flex-col gap-1 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 sm:flex-row sm:items-center sm:justify-between">
                    <span class="font-bold">7-day hosted trial is active</span>
                    <span>Ends {{ $layoutSubscription->trial_ends_at?->format('Y-m-d H:i') }}</span>
                </div>
            @endif
            @if (session('success'))
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@endif
</body>
</html>
