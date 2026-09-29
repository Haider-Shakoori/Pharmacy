<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <title>BusinessOS Pharmacy — Request a 7-Day Trial</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-900 antialiased">
<div class="relative isolate overflow-hidden">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,_rgba(20,184,166,0.22),_transparent_38%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.12),_transparent_32%)]"></div>

    <header class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 sm:px-8">
        <a href="{{ route('trial.request') }}" class="flex items-center gap-3 text-white">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-teal-500 text-lg font-black text-slate-950">Rx</span>
            <span>
                <span class="block font-bold">BusinessOS Pharmacy</span>
                <span class="block text-xs text-slate-400">Built for Afghanistan</span>
            </span>
        </a>
        <a href="{{ route('platform.login') }}" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-teal-400 hover:text-white">
            Platform login
        </a>
    </header>

    <main class="mx-auto grid max-w-7xl gap-10 px-5 pb-14 pt-6 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start lg:gap-14 lg:pb-20 lg:pt-12">
        <section class="pt-4 text-white lg:sticky lg:top-8">
            <span class="inline-flex rounded-full border border-teal-400/30 bg-teal-400/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-teal-300">
                7-day hosted trial
            </span>
            <h1 class="mt-6 max-w-2xl text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">
                Run your pharmacy with less paperwork and better control.
            </h1>
            <p class="mt-6 max-w-xl text-lg leading-8 text-slate-300">
                Request a trial account for BusinessOS Pharmacy. Our platform team reviews every request before a pharmacy workspace is created.
            </p>

            <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                @foreach ([
                    ['Web POS', 'Fast sales and invoice workflow'],
                    ['Inventory & batches', 'Stock, expiry and batch tracking'],
                    ['Daily closing', 'Cash and end-of-day control'],
                    ['Offline Android POS', 'Continue selling during internet outages'],
                    ['Dari / Pashto / English', 'Designed for Afghan pharmacy teams'],
                    ['AFN-first', 'Local currency and practical defaults'],
                ] as [$title, $description])
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-bold">{{ $title }}</p>
                        <p class="mt-1 text-sm leading-6 text-slate-400">{{ $description }}</p>
                    </article>
                @endforeach
            </div>

            <p class="mt-8 text-sm leading-6 text-slate-400">
                Submitting this form does not activate the account immediately. The platform team approves the request first, then provisioning and the 7-day trial begin.
            </p>
        </section>

        <section class="rounded-3xl bg-white p-5 shadow-2xl shadow-black/20 sm:p-8">
            <div>
                <p class="text-sm font-bold text-teal-700">Trial request</p>
                <h2 class="mt-1 text-3xl font-black tracking-tight">Create your request</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Use the owner details that should be connected to the pharmacy account.</p>
            </div>

            @if (session('success'))
                <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('trial.request.store') }}" class="mt-7 space-y-5">
                @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-bold">Pharmacy name</span>
                        <input name="pharmacy_name" value="{{ old('pharmacy_name') }}" required autocomplete="organization"
                               class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        @error('pharmacy_name')<span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold">Owner / contact name</span>
                        <input name="owner_name" value="{{ old('owner_name') }}" required autocomplete="name"
                               class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        @error('owner_name')<span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold">Phone / WhatsApp</span>
                        <input name="phone_whatsapp" value="{{ old('phone_whatsapp') }}" required autocomplete="tel" placeholder="+93..."
                               class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        @error('phone_whatsapp')<span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-bold">Owner email</span>
                        <input type="email" name="owner_email" value="{{ old('owner_email') }}" required autocomplete="email"
                               class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        @error('owner_email')<span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold">City / location</span>
                        <input name="location" value="{{ old('location') }}" required placeholder="Kabul, Afghanistan"
                               class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        @error('location')<span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold">Preferred language</span>
                        <select name="preferred_locale" class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                            <option value="en" @selected(old('preferred_locale') === 'en')>English</option>
                            <option value="fa" @selected(old('preferred_locale', 'fa') === 'fa')>Dari</option>
                            <option value="ps" @selected(old('preferred_locale') === 'ps')>Pashto</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold">Choose account password</span>
                        <input type="password" name="password" required autocomplete="new-password"
                               class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        @error('password')<span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold">Confirm password</span>
                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                               class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-bold">Notes <span class="font-normal text-slate-400">(optional)</span></span>
                        <textarea name="notes" rows="3" placeholder="Anything we should know about your pharmacy?"
                                  class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100">{{ old('notes') }}</textarea>
                        @error('notes')<span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                    </label>
                </div>

                <div class="hidden" aria-hidden="true">
                    <label>Website <input name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <button class="w-full rounded-xl bg-teal-700 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-teal-700/15 hover:bg-teal-800">
                    Request 7-day trial
                </button>
                <p class="text-center text-xs leading-5 text-slate-400">Your password is encrypted while the request is awaiting approval and is removed from the request after approval or rejection.</p>
            </form>
        </section>
    </main>
</div>
</body>
</html>
