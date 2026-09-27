@extends('layouts.pharmacy')

@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div class="mb-2 inline-flex rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-800">{{ $subscriptionHealth->label() }}</div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $tenant->name }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ __('pharmacy.dashboard') }} · {{ auth()->user()->name }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pharmacy code</p>
                <p class="mt-1 font-mono font-semibold text-slate-800">{{ $tenant->slug }}</p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-3 text-2xl font-bold tracking-tight">{{ $stat['value'] }}</p>
                <p class="mt-2 text-xs text-slate-400">Operational data activates in its scheduled batch.</p>
            </article>
        @endforeach
    </section>
</div>
@endsection
