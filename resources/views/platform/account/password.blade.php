@extends('layouts.platform')

@section('title', 'Change Password — Platform Admin')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <p class="text-sm font-semibold text-teal-700">Platform account</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">Change password</h2>
        <p class="mt-2 text-sm text-slate-500">
            Update the password used to sign in to the BusinessOS Pharmacy platform.
        </p>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <div class="mb-6 rounded-xl bg-slate-50 px-4 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Signed in as</p>
            <p class="mt-1 font-bold text-slate-900">{{ $admin->name }}</p>
            <p class="text-sm text-slate-500">{{ $admin->email }}</p>
        </div>

        <form method="POST" action="{{ route('platform.account.password.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <label class="block">
                <span class="text-sm font-bold text-slate-700">Current password</span>
                <input
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                >
                @error('current_password')
                    <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block">
                <span class="text-sm font-bold text-slate-700">New password</span>
                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                >
                <span class="mt-1 block text-xs text-slate-400">Use at least 8 characters.</span>
                @error('password')
                    <span class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block">
                <span class="text-sm font-bold text-slate-700">Confirm new password</span>
                <input
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-3.5 py-3 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                >
            </label>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs leading-5 text-slate-400">
                    Changing your password also rotates the remember-me token used by the platform account.
                </p>
                <button class="rounded-xl bg-teal-700 px-5 py-3 text-sm font-black text-white hover:bg-teal-800">
                    Change password
                </button>
            </div>
        </form>
    </section>
</div>
@endsection
