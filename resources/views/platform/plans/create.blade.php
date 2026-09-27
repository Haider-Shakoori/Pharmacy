@extends('layouts.platform')

@section('title', 'Add Subscription Plan — Platform Admin')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="mb-5">
        <p class="text-sm font-semibold text-teal-700">Commercial configuration</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">Add subscription plan</h2>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        @include('platform.plans._form')
    </div>
</div>
@endsection
