@extends('layouts.pharmacy')
@section('title', 'Edit customer — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div><p class="text-sm font-semibold text-teal-700">Customer accounts</p><h1 class="text-3xl font-bold">{{ $customer->name }}</h1></div>
    <form method="POST" action="{{ route('pharmacy.customers.update', $customer) }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        @csrf @method('PUT')
        @include('pharmacy.customers._form')
        <div class="flex gap-2"><button class="rounded-xl bg-teal-700 px-5 py-2.5 font-bold text-white">Save customer</button><a href="{{ route('pharmacy.customers.show', $customer) }}" class="rounded-xl border border-slate-300 px-5 py-2.5 font-semibold">View account</a></div>
    </form>
</div>
@endsection
