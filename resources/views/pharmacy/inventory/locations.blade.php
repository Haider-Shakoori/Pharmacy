@extends('layouts.pharmacy')

@section('title', 'Branches & Locations — Inventory')

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div><p class="text-sm font-semibold text-teal-700">Inventory setup</p><h1 class="mt-1 text-3xl font-bold">Branches & stock locations</h1><p class="mt-1 text-sm text-slate-500">Branches remain inside this pharmacy tenant; they are not separate SaaS tenants.</p></div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Add branch</h2>
            <form method="POST" action="{{ route('pharmacy.inventory.branches.store') }}" class="mt-4 grid gap-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2"><input name="code" required placeholder="Code" class="rounded-xl border border-slate-300 px-3 py-2.5"><input name="name" required placeholder="Branch name" class="rounded-xl border border-slate-300 px-3 py-2.5"></div>
                <input name="address" placeholder="Address" class="rounded-xl border border-slate-300 px-3 py-2.5">
                <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Create branch</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Add stock location</h2>
            <form method="POST" action="{{ route('pharmacy.inventory.locations.store') }}" class="mt-4 grid gap-3">
                @csrf
                <select name="branch_id" required class="rounded-xl border border-slate-300 px-3 py-2.5"><option value="">Branch</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <div class="grid gap-3 sm:grid-cols-2"><input name="code" required placeholder="Code" class="rounded-xl border border-slate-300 px-3 py-2.5"><input name="name" required placeholder="Location name" class="rounded-xl border border-slate-300 px-3 py-2.5"></div>
                <select name="kind" class="rounded-xl border border-slate-300 px-3 py-2.5">@foreach (['store','warehouse','shelf','quarantine','other'] as $kind)<option value="{{ $kind }}">{{ str($kind)->headline() }}</option>@endforeach</select>
                <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">Create location</button>
            </form>
        </section>
    </div>

    <div class="space-y-4">
        @foreach ($branches as $branch)
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex justify-between"><div><h2 class="font-bold">{{ $branch->name }}</h2><p class="text-xs text-slate-500">{{ $branch->code }}{{ $branch->is_default ? ' · Default' : '' }}</p></div></div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@forelse ($branch->locations as $location)<div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="font-semibold">{{ $location->name }}</p><p class="text-xs text-slate-500">{{ $location->code }} · {{ str($location->kind)->headline() }}{{ $location->is_default ? ' · Default' : '' }}</p></div>@empty<p class="text-sm text-slate-500">No stock locations.</p>@endforelse</div>
            </section>
        @endforeach
    </div>
</div>
@endsection
