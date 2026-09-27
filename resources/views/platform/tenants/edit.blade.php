@extends('layouts.platform')

@section('title', 'Edit '.$tenant->name.' — Platform Admin')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Tenant management</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Edit {{ $tenant->name }}</h2>
        </div>
        <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold">{{ $tenant->status->value }}</span>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        @include('platform.tenants._form')
    </div>
</div>
@endsection
