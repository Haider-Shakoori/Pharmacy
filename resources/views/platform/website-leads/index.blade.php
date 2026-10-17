@extends('layouts.platform')

@section('title', 'Demo Requests — Platform Admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-teal-700">Sales / website enquiries</p>
            <h2 class="mt-1 text-3xl font-black">Demo Requests</h2>
            <p class="mt-2 text-sm text-slate-500">Private requests received from darmaltoon.com. Website request IDs prevent repeated imports.</p>
        </div>
        <div class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm font-bold text-teal-900">
            {{ number_format($newCount) }} new genuine requests
        </div>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5" action="{{ route('platform.demo-requests.index') }}">
        <div class="lg:col-span-2">
            <label for="search" class="mb-1 block text-xs font-bold text-slate-600">Name, pharmacy, phone or email</label>
            <input id="search" name="search" value="{{ $search }}" maxlength="100" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Search requests">
        </div>
        <div>
            <label for="kind" class="mb-1 block text-xs font-bold text-slate-600">Type</label>
            <select name="kind" id="kind" class="w-full rounded-lg border-slate-300 text-sm">
                <option value="">All</option>
                <option value="demo" @selected($kind === 'demo')>Demo</option>
                <option value="contact" @selected($kind === 'contact')>Contact</option>
            </select>
        </div>
        <div>
            <label for="status" class="mb-1 block text-xs font-bold text-slate-600">Status</label>
            <select name="status" id="status" class="w-full rounded-lg border-slate-300 text-sm">
                <option value="">All</option>
                @foreach (\App\Models\WebsiteLead::STATUSES as $value)
                    <option value="{{ $value }}" @selected($status === $value)>{{ str($value)->replace('_',' ')->title() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="test" class="mb-1 block text-xs font-bold text-slate-600">Show</label>
            <select name="test" id="test" class="w-full rounded-lg border-slate-300 text-sm">
                <option value="" @selected($test !== 'yes')>Genuine requests</option>
                <option value="yes" @selected($test === 'yes')>Test requests only</option>
            </select>
        </div>
        <div class="lg:col-span-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-bold text-white">Filter</button>
            <a href="{{ route('platform.demo-requests.index') }}" class="text-sm font-semibold text-slate-600">Clear filters</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Received</th>
                    <th class="px-4 py-3">Pharmacy / contact</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Follow-up</th>
                    <th class="px-4 py-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($leads as $lead)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $lead->submitted_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-4">
                            <p class="font-bold text-slate-900">{{ $lead->pharmacy ?: 'Not supplied' }}</p>
                            <p class="text-slate-600">{{ $lead->contact_name }}</p>
                            @if($lead->is_test)<span class="text-xs font-semibold text-amber-700">Test request</span>@endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">{{ $lead->phone }}</td>
                        <td class="px-4 py-4 capitalize">{{ $lead->kind }}</td>
                        <td class="px-4 py-4">{{ str($lead->status)->replace('_',' ')->title() }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-slate-500">{{ $lead->follow_up_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-4"><a class="font-bold text-teal-700 hover:underline" href="{{ route('platform.demo-requests.show', $lead) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">
                        No requests found. If the website has received a submission, check the private lead sync configuration and scheduled importer.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $leads->links() }}
</div>
@endsection
