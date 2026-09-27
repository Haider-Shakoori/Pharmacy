@extends('layouts.pharmacy')

@section('title', 'Medicine Setup — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-teal-700">Medicine catalog</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight">Categories & Manufacturers</h1>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Categories</h2>
            <form method="POST" action="{{ route('pharmacy.medicine-references.store', 'category') }}" class="mt-4 flex gap-2">
                @csrf
                <input name="name" placeholder="Category name" required class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5">
                <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add</button>
            </form>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($categories as $item)
                    <div class="flex justify-between py-2.5 text-sm"><span>{{ $item->name }}</span><span class="text-slate-400">{{ $item->medicines_count }} medicines</span></div>
                @empty
                    <p class="py-4 text-sm text-slate-500">No categories yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Manufacturers</h2>
            <form method="POST" action="{{ route('pharmacy.medicine-references.store', 'manufacturer') }}" class="mt-4 grid gap-2 sm:grid-cols-[1fr_150px_auto]">
                @csrf
                <input name="name" placeholder="Manufacturer" required class="rounded-xl border border-slate-300 px-3 py-2.5">
                <input name="country" placeholder="Country" class="rounded-xl border border-slate-300 px-3 py-2.5">
                <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">Add</button>
            </form>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($manufacturers as $item)
                    <div class="flex justify-between py-2.5 text-sm">
                        <span>{{ $item->name }} <span class="text-slate-400">{{ $item->country ? '· '.$item->country : '' }}</span></span>
                        <span class="text-slate-400">{{ $item->medicines_count }} medicines</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500">No manufacturers yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
