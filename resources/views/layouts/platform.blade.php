<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', 'Platform Admin — BusinessOS Pharmacy')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
<div class="min-h-screen lg:flex">
    <aside class="w-full border-b border-slate-800 bg-slate-950 text-white lg:min-h-screen lg:w-72 lg:border-b-0 lg:border-e">
        <div class="flex items-center justify-between gap-4 px-5 py-5 lg:block">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-teal-300">BusinessOS Pharmacy</p>
                <h1 class="mt-1 text-lg font-bold">Platform Control Plane</h1>
            </div>
            <div class="flex items-center gap-2 lg:mt-4">
                <a href="{{ \App\Support\PlatformRoute::url('account.password.edit') }}"
                   class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold {{ \App\Support\PlatformRoute::is('account.*') ? 'border-teal-500 bg-teal-500/10 text-teal-200' : 'text-slate-200 hover:bg-slate-900' }}">
                    Change password
                </a>
                <form method="POST" action="{{ \App\Support\PlatformRoute::url('logout') }}">
                    @csrf
                    <button class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-900">Sign out</button>
                </form>
            </div>
        </div>

        <nav class="flex gap-2 overflow-x-auto px-4 pb-4 text-sm lg:block lg:space-y-1 lg:overflow-visible lg:pb-0">
            <a href="{{ \App\Support\PlatformRoute::url('dashboard') }}" class="block whitespace-nowrap rounded-lg px-3 py-2.5 {{ \App\Support\PlatformRoute::is('dashboard') ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-slate-900' }}">Dashboard</a>
            <a href="{{ \App\Support\PlatformRoute::url('tenants.index') }}" class="block whitespace-nowrap rounded-lg px-3 py-2.5 {{ \App\Support\PlatformRoute::is('tenants.*') ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-slate-900' }}">Pharmacies</a>
            <a href="{{ \App\Support\PlatformRoute::url('trial-requests.index') }}" class="flex items-center justify-between gap-3 whitespace-nowrap rounded-lg px-3 py-2.5 {{ \App\Support\PlatformRoute::is('trial-requests.*') ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-slate-900' }}">
                <span>Trial Requests</span>
                @php($pendingTrialRequests = \App\Models\TrialRequest::query()->where('status', 'pending')->count())
                @if ($pendingTrialRequests > 0)
                    <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-black text-slate-950">{{ $pendingTrialRequests }}</span>
                @endif
            </a>
            <a href="{{ \App\Support\PlatformRoute::url('plans.index') }}" class="block whitespace-nowrap rounded-lg px-3 py-2.5 {{ \App\Support\PlatformRoute::is('plans.*') ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-slate-900' }}">Plans</a>
            <a href="{{ \App\Support\PlatformRoute::url('subscriptions.index') }}" class="block whitespace-nowrap rounded-lg px-3 py-2.5 {{ \App\Support\PlatformRoute::is('subscriptions.*') ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-slate-900' }}">Subscriptions</a>
            <a href="{{ \App\Support\PlatformRoute::url('monitoring.index') }}" class="block whitespace-nowrap rounded-lg px-3 py-2.5 {{ \App\Support\PlatformRoute::is('monitoring.*') ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-slate-900' }}">Monitoring</a>
            <a href="{{ \App\Support\PlatformRoute::url('licenses.index') }}" class="block whitespace-nowrap rounded-lg px-3 py-2.5 {{ \App\Support\PlatformRoute::is('licenses.*') ? 'bg-teal-700 text-white' : 'text-slate-300 hover:bg-slate-900' }}">Licenses</a>
        </nav>
    </aside>

    <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
        @if (session('success'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ $errors->first() }}
            </div>
        @endif
        @if (session('generated_license_key'))
            <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950">
                <p class="text-sm font-bold">Activation key — copy it now</p>
                <p class="mt-1 text-xs">For security, plaintext is never stored. Windows activation keys are single-use: after the first successful PC activation this key cannot be used again.</p>
                <code class="mt-3 block break-all rounded-lg bg-white px-3 py-2 text-sm font-bold">{{ session('generated_license_key') }}</code>
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
