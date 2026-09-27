@extends('layouts.pharmacy')

@section('title', __('pharmacy.dashboard').' — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div class="mb-2 inline-flex rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-800">
                    {{ __('pharmacy.foundation_ready') }}
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('pharmacy.dashboard') }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    The pharmacy workspace is now part of the project foundation. Operational modules will activate batch-by-batch without changing the core low-bandwidth and offline-first architecture.
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('pharmacy.deployment_target') }}</p>
                <p class="mt-1 font-mono font-semibold text-slate-800">{{ config('pharmacy.deployment_host') }}</p>
                <p class="mt-1 text-xs text-slate-500">Deployment scheduled for Batch 12.</p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-3 text-2xl font-bold tracking-tight">{{ $stat['value'] }}</p>
                <p class="mt-2 text-xs text-slate-400">Module data will activate in its scheduled batch.</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        @foreach ([
            ['2', 'Tenant isolation', 'Every pharmacy will operate inside a strictly isolated tenant context.'],
            ['5', 'License engine', 'Signed activation and subscription enforcement will be introduced before operational pharmacy data.'],
            ['12', 'Web POS + deployment', 'The web sales interface becomes operational and the application is deployed to pharmacy.businessos.af.'],
        ] as [$batch, $title, $description])
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-bold">{{ $title }}</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Batch {{ $batch }}</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $description }}</p>
            </article>
        @endforeach
    </section>
</div>
@endsection
