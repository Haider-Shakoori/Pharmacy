@extends('layouts.pharmacy')
@section('title', 'Edit '.$user->name.' — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-3xl"><div class="mb-5"><p class="text-sm font-semibold text-teal-700">Access control</p><h1 class="text-3xl font-bold">Edit {{ $user->name }}</h1></div><div class="rounded-2xl border border-slate-200 bg-white p-6">@include('pharmacy.users._form')</div></div>
@endsection
