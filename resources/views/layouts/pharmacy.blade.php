<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), config('pharmacy.rtl_locales'), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07152D">
    <title>@yield('title', __('pharmacy.product'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class', 'bg-slate-50 text-slate-900 antialiased')">
@hasSection('fullscreen')
    @yield('content')
@else
@php
    $layoutTenant = app(\App\Support\Tenancy\TenantContext::class)->tenant();
    $layoutSubscription = $layoutTenant->subscription()->first();
    $routeLabel = match (true) {
        request()->routeIs('pharmacy.dashboard') => __('pharmacy.nav.dashboard'),
        request()->routeIs('pharmacy.pos.*') => __('pharmacy.nav.pos'),
        request()->routeIs('pharmacy.medicines.*', 'pharmacy.medicine-references.*') => __('pharmacy.nav.medicines'),
        request()->routeIs('pharmacy.inventory.*') => __('pharmacy.nav.inventory'),
        request()->routeIs('pharmacy.customers.*') => __('pharmacy.nav.customers'),
        request()->routeIs('pharmacy.purchase-*', 'pharmacy.suppliers.*', 'pharmacy.supplier-payments.*') => 'Purchasing',
        request()->routeIs('pharmacy.accounting.*') => 'Accounting',
        request()->routeIs('pharmacy.reports.*') => __('pharmacy.nav.reports'),
        request()->routeIs('pharmacy.settings.*') => __('pharmacy.nav.settings'),
        default => __('pharmacy.product'),
    };
@endphp
<div class="dt-shell lg:flex" x-data="{ mobileNav: false, compact: false, profileOpen: false, init() { try { this.compact = localStorage.getItem('darmaltoon.sidebar.compact') === '1' } catch(e) {} }, toggleSidebar() { this.compact = !this.compact; try { localStorage.setItem('darmaltoon.sidebar.compact', this.compact ? '1' : '0') } catch(e) {} } }">
    <aside id="desktopPharmacySidebar" class="dt-sidebar hidden shrink-0 lg:block" :class="{ 'dt-sidebar-compact': compact }">
        @include('pharmacy.partials.sidebar')
    </aside>

    <div class="min-w-0 flex-1">
        <header class="dt-topbar sticky top-0 z-30">
            <div class="flex h-[72px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-7">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="dt-icon-button hidden lg:grid" @click="toggleSidebar()" :aria-expanded="(!compact).toString()" aria-controls="desktopPharmacySidebar" aria-label="Toggle sidebar">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <button type="button" class="dt-icon-button grid lg:hidden" @click="mobileNav = !mobileNav" aria-label="Menu" :aria-expanded="mobileNav.toString()" aria-controls="mobilePharmacyNav">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-[15px] font-black text-slate-950">{{ $routeLabel }}</p>
                            @if ($layoutSubscription?->status?->value === 'trial')
                                <span class="hidden rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-sky-700 sm:inline">Trial</span>
                            @endif
                        </div>
                        <p class="truncate text-xs font-medium text-slate-500">{{ $layoutTenant->name }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if(auth()->user()->hasPermission('pos.sell'))
                        <a href="{{ route('pharmacy.pos.index') }}" class="dt-top-pos hidden sm:inline-flex">+ {{ __('pharmacy.nav.pos') }}</a>
                    @endif
                    <div class="dt-language hidden items-center rounded-xl bg-slate-100 p-1 md:flex">
                        @foreach (['en' => 'EN', 'fa' => 'دری', 'ps' => 'پښتو'] as $locale => $label)
                            <a href="{{ route('pharmacy.locale.switch', $locale) }}" class="{{ app()->getLocale() === $locale ? 'is-active' : '' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                    @if(auth()->user()->hasPermission('dashboard.view'))
                        <a href="{{ route('pharmacy.alerts.index') }}" class="dt-icon-button relative grid" aria-label="{{ __('pharmacy.nav.alerts') }}">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                        </a>
                    @endif
                    <div class="relative" @click.outside="profileOpen=false">
                        <button type="button" @click="profileOpen=!profileOpen" class="dt-profile-button">
                            <span class="dt-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="hidden max-w-28 truncate text-start text-xs font-bold text-slate-700 sm:block">{{ auth()->user()->name }}</span>
                            <span class="hidden text-slate-400 sm:block">⌄</span>
                        </button>
                        <div x-cloak x-show="profileOpen" x-transition class="dt-profile-menu absolute end-0 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                            <div class="border-b border-slate-100 px-3 py-2 md:hidden">
                                <div class="flex gap-1">@foreach (['en'=>'EN','fa'=>'دری','ps'=>'پښتو'] as $locale=>$label)<a href="{{ route('pharmacy.locale.switch',$locale) }}" class="rounded-lg px-2 py-1 text-xs font-bold {{ app()->getLocale()===$locale ? 'bg-blue-50 text-blue-700' : 'text-slate-500' }}">{{ $label }}</a>@endforeach</div>
                            </div>
                            @if(auth()->user()->hasPermission('settings.manage'))<a href="{{ route('pharmacy.settings.edit') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('pharmacy.nav.settings') }}</a>@endif
                            <form method="POST" action="{{ route('pharmacy.logout') }}">@csrf<button class="w-full rounded-xl px-3 py-2.5 text-start text-sm font-bold text-red-600 hover:bg-red-50">{{ __('pharmacy.actions.sign_out') !== 'pharmacy.actions.sign_out' ? __('pharmacy.actions.sign_out') : 'Sign out' }}</button></form>
                        </div>
                    </div>
                </div>
            </div>
            <div id="mobilePharmacyNav" class="dt-mobile-panel border-t border-slate-200 bg-white lg:hidden" x-show="mobileNav" x-cloak>
                @include('pharmacy.partials.mobile-nav')
            </div>
        </header>

        <main class="dt-main px-4 py-5 sm:px-6 lg:px-7 lg:py-6">
            @if ($layoutSubscription?->status?->value === 'trial')
                <div class="dt-notice dt-notice-info mb-5"><strong>7-day hosted trial is active</strong><span>Ends {{ $layoutSubscription->trial_ends_at?->format('Y-m-d H:i') }}</span></div>
            @endif
            @if (session('success'))
                <div class="dt-notice dt-notice-success mb-5">{{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@endif
</body>
</html>
