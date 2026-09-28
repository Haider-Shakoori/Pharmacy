<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BusinessOS Pharmacy Offline — License</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
<div class="mx-auto flex min-h-screen max-w-5xl items-center px-4 py-10 sm:px-6">
    <div class="grid w-full overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl lg:grid-cols-[1.1fr_.9fr]">
        <section class="p-6 sm:p-9">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-emerald-400">BusinessOS Pharmacy Offline</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">License activation</h1>
            <p class="mt-3 max-w-xl text-sm leading-6 text-slate-400">This computer must connect to BusinessOS once to activate or renew. After activation, Pharmacy continues working without internet until the signed license expires.</p>

            @if(session('success'))
                <div class="mt-5 rounded-2xl border border-emerald-700/50 bg-emerald-950/60 px-4 py-3 text-sm font-semibold text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="mt-5 rounded-2xl border border-amber-700/50 bg-amber-950/60 px-4 py-3 text-sm font-semibold text-amber-200">{{ session('warning') }}</div>
            @endif
            @if($errors->any())
                <div class="mt-5 rounded-2xl border border-red-700/50 bg-red-950/60 px-4 py-3 text-sm font-semibold text-red-200">{{ $errors->first() }}</div>
            @endif

            <div class="mt-7 grid gap-3 sm:grid-cols-2">
                <article class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Status</p>
                    <p class="mt-2 text-xl font-black {{ $status['valid'] ? 'text-emerald-400' : 'text-amber-400' }}">{{ $status['valid'] ? 'Active' : 'Activation required' }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $status['message'] }}</p>
                </article>
                <article class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Expires</p>
                    <p class="mt-2 text-xl font-black">{{ isset($status['payload']['expires_at']) ? date('Y-m-d', (int) $status['payload']['expires_at']) : '—' }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ $status['payload']['plan_code'] ?? 'No active offline plan' }}</p>
                </article>
            </div>

            <form method="POST" action="{{ route('offline.license.activate') }}" class="mt-7 space-y-3">
                @csrf
                <label class="block">
                    <span class="text-sm font-bold">Offline license key</span>
                    <input name="license_key" required autocomplete="off" placeholder="PHM-…" class="mt-2 w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 font-mono text-sm outline-none ring-emerald-500 focus:ring-2">
                </label>
                <button class="w-full rounded-2xl bg-emerald-500 px-5 py-3.5 text-sm font-black text-slate-950 hover:bg-emerald-400">Activate / Refresh License</button>
            </form>

            @if($status['valid'])
                <a href="/" class="mt-3 block w-full rounded-2xl border border-slate-700 px-5 py-3 text-center text-sm font-bold hover:bg-slate-800">Open Pharmacy</a>
            @endif
        </section>

        <aside class="border-t border-slate-800 bg-slate-950/70 p-6 sm:p-9 lg:border-s lg:border-t-0">
            <h2 class="text-lg font-black">Installation identity</h2>
            <dl class="mt-5 space-y-5 text-sm">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Installation ID</dt>
                    <dd class="mt-2 break-all font-mono text-xs text-slate-300">{{ $installationId }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Machine fingerprint</dt>
                    <dd class="mt-2 break-all font-mono text-xs text-slate-300">{{ $fingerprint }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Licensed pharmacy</dt>
                    <dd class="mt-2 font-bold">{{ $status['payload']['pharmacy_name'] ?? '—' }}</dd>
                </div>
            </dl>
            <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-900 p-4 text-xs leading-5 text-slate-400">
                Changing the signed license file, expiry date, installation ID or machine binding invalidates the license. If this computer is replaced, transfer the installation from the BusinessOS platform.
            </div>
        </aside>
    </div>
</div>
</body>
</html>
