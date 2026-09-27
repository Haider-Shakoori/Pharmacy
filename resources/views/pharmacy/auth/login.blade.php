<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pharmacy Login — BusinessOS Pharmacy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-950 p-4">
    <main class="w-full max-w-md rounded-2xl border border-slate-800 bg-white p-6 shadow-2xl sm:p-8">
        <div class="mb-6 flex items-center gap-3">
            <div class="grid h-12 w-12 place-items-center rounded-xl bg-teal-700 text-lg font-black text-white">Rx</div>
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-teal-700">BusinessOS Pharmacy</p>
                <h1 class="text-2xl font-bold">Pharmacy sign in</h1>
            </div>
        </div>

        <form method="POST" action="{{ route('pharmacy.login.store') }}" class="space-y-4">
            @csrf

            <label class="block">
                <span class="text-sm font-semibold">Pharmacy ID</span>
                <input name="tenant" value="{{ old('tenant') }}" required autofocus placeholder="e.g. demo-pharmacy"
                       class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-teal-600">
                <span class="mt-1 block text-xs text-slate-500">Use the pharmacy tenant ID supplied during onboarding.</span>
            </label>

            <label class="block">
                <span class="text-sm font-semibold">Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required
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
                Remember me
            </label>

            <button class="w-full rounded-xl bg-teal-700 px-4 py-3 font-bold text-white hover:bg-teal-800">
                Sign in
            </button>
        </form>
    </main>
</body>
</html>
