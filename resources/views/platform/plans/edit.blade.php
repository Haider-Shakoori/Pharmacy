@extends('layouts.platform')

@section('title', 'Edit '.$plan->name.' — Platform Admin')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="mb-5 flex items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-teal-700">Commercial configuration</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Edit {{ $plan->name }}</h2>
        </div>
        <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold">{{ $plan->subscriptions()->count() }} subscriptions</span>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        @include('platform.plans._form')
    </div>
</div>
@endsection
