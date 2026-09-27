@extends('layouts.pharmacy')
@section('title', 'Role: '.$role->name.' — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-4xl"><div class="mb-5"><p class="text-sm font-semibold text-teal-700">Access control</p><h1 class="text-3xl font-bold">{{ $role->name }}</h1><p class="text-xs text-slate-500">{{ $role->code }}</p></div><form method="POST" action="{{ route('pharmacy.roles.update', $role) }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6">@csrf @method('PUT')@if($role->code === 'owner')<div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">The Owner role is protected and always keeps all permissions.</div>@endif @include('pharmacy.roles._permissions')<button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white" {{ $role->code === 'owner' ? 'disabled' : '' }}>Save permissions</button></form></div>
@endsection
