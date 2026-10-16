<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), config('pharmacy.rtl_locales'), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('pharmacy.login.title') }} — {{ __('pharmacy.product') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-950 p-4">
    <main class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl sm:p-8">
        <div class="mb-6">
            <div class="flex items-center justify-between gap-3">
                <div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-700 text-lg font-black text-white">Rx</div>
                <nav class="flex flex-wrap items-center gap-1" aria-label="{{ __('pharmacy.login.languages') }}">
                    @foreach (['en' => 'EN', 'fa' => 'دری', 'ps' => 'پښتو'] as $locale => $label)
                        <a href="{{ route('pharmacy.locale.switch', $locale) }}" lang="{{ $locale }}"
                           aria-current="{{ app()->getLocale() === $locale ? 'true' : 'false' }}"
                           class="rounded-lg px-2.5 py-1.5 text-sm font-semibold {{ app()->getLocale() === $locale ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            <p class="mt-4 text-xs font-bold tracking-wider text-blue-700">BusinessOS Pharmacy</p>
            <h1 class="mt-1 text-2xl font-bold">{{ __('pharmacy.login.heading') }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('pharmacy.login.subtitle') }}</p>
        </div>

        <form method="POST" action="{{ route('pharmacy.login.store') }}" class="space-y-4">
            @csrf

            <label class="block">
                <span class="text-sm font-semibold">{{ __('pharmacy.login.email') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                @error('email')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="text-sm font-semibold">{{ __('pharmacy.login.password') }}</span>
                <input type="password" name="password" required autocomplete="current-password"
                       class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5">
            </label>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1">
                {{ __('pharmacy.login.remember') }}
            </label>

            <button type="submit" class="w-full rounded-xl bg-blue-700 px-4 py-3 font-bold text-white">{{ __('pharmacy.login.submit') }}</button>
        </form>
    </main>
</body>
</html>
