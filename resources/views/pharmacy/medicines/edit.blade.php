@extends('layouts.pharmacy')

@section('title', 'Edit '.$medicine->brand_name.' — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="mb-5 text-3xl font-bold tracking-tight">Edit {{ $medicine->brand_name }}</h1>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">@include('pharmacy.medicines._form')</div>
</div>
@endsection
