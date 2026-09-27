@extends('layouts.pharmacy')
@section('title', 'Add Role — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-4xl"><div class="mb-5"><p class="text-sm font-semibold text-teal-700">Access control</p><h1 class="text-3xl font-bold">Add custom role</h1></div><form method="POST" action="{{ route('pharmacy.roles.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">@csrf<div class="grid gap-4 sm:grid-cols-2"><label><span class="text-sm font-semibold">Role name</span><input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label><label><span class="text-sm font-semibold">Code</span><input name="code" value="{{ old('code') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5"></label></div>@include('pharmacy.roles._permissions')<button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Create role</button></form></div>
@endsection
