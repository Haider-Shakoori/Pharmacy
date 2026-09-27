@extends('layouts.pharmacy')
@section('title', 'Edit '.$supplier->name.' — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-4xl"><h1 class="mb-5 text-3xl font-bold">Edit {{ $supplier->name }}</h1><div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">@include('pharmacy.purchasing.suppliers._form')</div></div>
@endsection
