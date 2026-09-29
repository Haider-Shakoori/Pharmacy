@extends('layouts.platform')

@section('title', 'Trial Requests — Platform Admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Customer onboarding</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Trial requests</h2>
            <p class="mt-2 text-sm text-slate-500">Review public pharmacy requests before provisioning a tenant and starting its 7-day trial.</p>
        </div>
        <a href="{{ route('trial.request') }}" target="_blank" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700">
            Open public request page
        </a>
    </div>

    <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-4">
        <span class="me-2 text-sm font-bold text-slate-600">Status</span>
        @foreach (['' => 'All', 'pending' => 'Pending', 'provisioning' => 'Provisioning', 'failed' => 'Failed', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
            <a href="{{ route('platform.trial-requests.index', $value === '' ? [] : ['status' => $value]) }}"
               class="rounded-lg px-3 py-2 text-xs font-bold {{ $status === $value ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($trialRequests as $trialRequest)
            @php
                $statusClass = match ($trialRequest->status) {
                    'pending' => 'bg-amber-100 text-amber-800',
                    'approved' => 'bg-emerald-100 text-emerald-800',
                    'rejected' => 'bg-slate-200 text-slate-700',
                    'failed' => 'bg-red-100 text-red-700',
                    'provisioning' => 'bg-sky-100 text-sky-800',
                    default => 'bg-slate-100 text-slate-700',
                };
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-xl font-black">{{ $trialRequest->pharmacy_name }}</h3>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ ucfirst($trialRequest->status) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">Requested {{ $trialRequest->created_at->format('Y-m-d H:i') }} · proposed URL {{ $trialRequest->requested_slug }}.{{ config('pharmacy.deployment_host') }}</p>

                        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Owner</dt>
                                <dd class="mt-1 font-semibold">{{ $trialRequest->owner_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Email</dt>
                                <dd class="mt-1 break-all font-semibold">{{ $trialRequest->owner_email }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Phone / WhatsApp</dt>
                                <dd class="mt-1 font-semibold">{{ $trialRequest->phone_whatsapp }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Location / language</dt>
                                <dd class="mt-1 font-semibold">{{ $trialRequest->location }} · {{ strtoupper($trialRequest->preferred_locale) }}</dd>
                            </div>
                        </dl>

                        @if ($trialRequest->notes)
                            <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                <span class="font-bold text-slate-800">Applicant note:</span> {{ $trialRequest->notes }}
                            </div>
                        @endif

                        @if ($trialRequest->failure_reason)
                            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                <span class="font-bold">Last provisioning error:</span> {{ $trialRequest->failure_reason }}
                            </div>
                        @endif

                        @if ($trialRequest->tenant)
                            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                                <span class="font-semibold text-slate-500">Tenant:</span>
                                <a href="{{ route('platform.tenants.edit', $trialRequest->tenant) }}" class="font-bold text-teal-700">
                                    {{ $trialRequest->tenant->name ?? $trialRequest->requested_slug }}
                                </a>
                                <span class="text-slate-400">{{ str_replace('_', ' ', $trialRequest->tenant->provisioning_status) }}</span>
                            </div>
                        @endif
                    </div>

                    @if (in_array($trialRequest->status, ['pending', 'failed', 'provisioning'], true))
                        <div class="w-full shrink-0 space-y-3 lg:w-72">
                            <form method="POST" action="{{ route('platform.trial-requests.approve', $trialRequest) }}">
                                @csrf
                                <button class="w-full rounded-xl bg-emerald-500 px-4 py-3 text-sm font-black text-slate-950 hover:bg-emerald-400">
                                    {{ $trialRequest->status === 'pending' ? 'Approve + create trial account' : 'Retry approval / provisioning' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('platform.trial-requests.reject', $trialRequest) }}" class="rounded-xl border border-slate-200 p-3">
                                @csrf
                                <textarea name="decision_notes" rows="2" placeholder="Rejection note (optional)"
                                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                                <button class="mt-2 w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Reject request</button>
                            </form>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <p class="font-bold text-slate-700">No trial requests found.</p>
                <p class="mt-1 text-sm text-slate-500">New requests submitted from the pharmacy root page will appear here.</p>
            </div>
        @endforelse
    </div>

    {{ $trialRequests->links() }}
</div>
@endsection
