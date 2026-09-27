@extends('layouts.pharmacy')
@section('title', 'Roles — '.__('pharmacy.product'))
@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div class="flex items-end justify-between gap-3"><div><p class="text-sm font-semibold text-teal-700">Access control</p><h1 class="text-3xl font-bold">Roles & Permissions</h1></div><a href="{{ route('pharmacy.roles.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add custom role</a></div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">@foreach($roles as $role)<article class="rounded-2xl border border-slate-200 bg-white p-5"><div class="flex justify-between gap-3"><h2 class="font-bold">{{ $role->name }}</h2>@if($role->is_system)<span class="text-xs font-bold text-slate-500">System</span>@endif</div><p class="mt-2 text-xs text-slate-500">{{ $role->permissions_count }} permissions · {{ $role->users_count }} users</p><a href="{{ route('pharmacy.roles.edit', $role) }}" class="mt-4 inline-block text-sm font-bold text-teal-700">Manage permissions</a></article>@endforeach</div>
</div>
@endsection
