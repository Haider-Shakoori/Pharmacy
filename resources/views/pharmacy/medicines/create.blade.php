@extends('layouts.pharmacy')

@section('title', 'Add Medicine — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="mb-5 text-3xl font-bold tracking-tight">Add medicine</h1>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">@include('pharmacy.medicines._form')</div>
</div>
@endsection
