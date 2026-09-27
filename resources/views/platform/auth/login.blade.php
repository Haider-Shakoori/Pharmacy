<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Platform Login — BusinessOS Pharmacy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-950 p-4">
    <main class="w-full max-w-md rounded-2xl border border-slate-800 bg-white p-6 shadow-2xl sm:p-8">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-teal-700">BusinessOS Pharmacy</p>
        <h1 class="mt-2 text-2xl font-bold">Platform administrator</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">SaaS owner control plane. Pharmacy staff use the pharmacy workspace instead.</p>

        <form method="POST" action="{{ route('platform.login.store') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="text-sm font-semibold">Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-teal-600">
                @error('email')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block">
                <span class="text-sm font-semibold">Password</span>
                <input type="password" name="password" required
                       class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-teal-600">
            </label>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                Remember this browser
            </label>

            <button class="w-full rounded-xl bg-teal-700 px-4 py-3 font-bold text-white hover:bg-teal-800">
                Sign in to platform
            </button>
        </form>
    </main>
</body>
</html>
