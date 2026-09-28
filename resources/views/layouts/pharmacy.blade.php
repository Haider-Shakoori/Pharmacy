<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), config('pharmacy.rtl_locales'), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <title>@yield('title', __('pharmacy.product'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
<div class="min-h-screen lg:flex" x-data="{ mobileNav: false }">
    <aside class="hidden w-72 shrink-0 border-e border-slate-200 bg-white lg:block">
        @include('pharmacy.partials.sidebar')
    </aside>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button class="rounded-lg border border-slate-200 p-2 lg:hidden" @click="mobileNav = !mobileNav" aria-label="Menu">
                        <span class="block h-0.5 w-5 bg-slate-700"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-slate-700"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-slate-700"></span>
                    </button>
                    <div>
                        <p class="text-sm font-semibold">{{ app(\App\Support\Tenancy\TenantContext::class)->tenant()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->name }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs font-semibold">
                    @foreach (['en' => 'EN', 'fa' => 'دری', 'ps' => 'پښتو'] as $locale => $label)
                        <a href="{{ route('pharmacy.locale.switch', $locale) }}"
                           class="rounded-md px-2.5 py-1.5 {{ app()->getLocale() === $locale ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $label }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('pharmacy.logout') }}">
                        @csrf
                        <button class="rounded-md bg-slate-900 px-2.5 py-1.5 text-white">Sign out</button>
                    </form>
                </div>
            </div>
            <div class="border-t border-slate-100 bg-white lg:hidden" x-show="mobileNav" x-cloak>
                @include('pharmacy.partials.mobile-nav')
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
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
</body>
</html>
