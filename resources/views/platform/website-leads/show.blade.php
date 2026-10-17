@extends('layouts.platform')

@section('title', 'Request Details — Platform Admin')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <div>
        <a href="{{ route('platform.demo-requests.index') }}" class="text-sm font-bold text-teal-700">← Demo Requests</a>
        <h2 class="mt-2 text-3xl font-black">{{ $lead->pharmacy ?: $lead->contact_name }}</h2>
        <p class="mt-1 text-sm text-slate-500">
            {{ ucfirst($lead->kind) }} #{{ $lead->source_id }} · Received {{ $lead->submitted_at?->format('Y-m-d H:i') }} · {{ strtoupper($lead->language) }}
            @if($lead->is_test) · Test request @endif
        </p>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h3 class="mb-4 text-lg font-black">Contact details</h3>
            <dl class="space-y-3 text-sm">
                <div><dt class="font-bold text-slate-500">Name</dt><dd>{{ $lead->contact_name }}</dd></div>
                <div><dt class="font-bold text-slate-500">Phone / WhatsApp</dt><dd>{{ $lead->phone }}</dd></div>
                <div><dt class="font-bold text-slate-500">Email</dt><dd>{{ $lead->email ?: 'Not provided' }}</dd></div>
                <div><dt class="font-bold text-slate-500">Pharmacy</dt><dd>{{ $lead->pharmacy ?: 'Not provided' }}</dd></div>
                <div><dt class="font-bold text-slate-500">Message</dt><dd class="whitespace-pre-wrap break-words">{{ $lead->message ?: '—' }}</dd></div>
                @foreach(($lead->details ?? []) as $key => $value)
                    @if(is_scalar($value))
                        <div><dt class="font-bold text-slate-500">{{ str($key)->headline() }}</dt><dd class="break-words">{{ $value }}</dd></div>
                    @endif
                @endforeach
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h3 class="mb-4 text-lg font-black">Follow-up &amp; classification</h3>
            <form method="POST" action="{{ route('platform.demo-requests.update', $lead) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="status" class="mb-1 block text-sm font-bold text-slate-700">Status</label>
                    <select id="status" name="status" class="w-full rounded-lg border-slate-300 text-sm" required>
                        @foreach(\App\Models\WebsiteLead::STATUSES as $value)
                            <option value="{{ $value }}" @selected(old('status', $lead->status) === $value)>{{ str($value)->replace('_',' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="is_test" class="mb-1 block text-sm font-bold text-slate-700">Classification</label>
                    <select id="is_test" name="is_test" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="0" @selected(!old('is_test', $lead->is_test))>Genuine prospect</option>
                        <option value="1" @selected((bool)old('is_test', $lead->is_test))>Test / internal submission (excluded from lead counts)</option>
                    </select>
                </div>
                <div>
                    <label for="follow_up_at" class="mb-1 block text-sm font-bold text-slate-700">Next follow-up date</label>
                    <input type="datetime-local" name="follow_up_at" id="follow_up_at"
                        value="{{ old('follow_up_at', $lead->follow_up_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label for="internal_notes" class="mb-1 block text-sm font-bold text-slate-700">Private notes</label>
                    <textarea id="internal_notes" name="internal_notes" rows="5" maxlength="4000"
                        class="w-full rounded-lg border-slate-300 text-sm">{{ old('internal_notes', $lead->internal_notes) }}</textarea>
                </div>
                <button class="rounded-lg bg-teal-700 px-5 py-3 text-sm font-bold text-white" type="submit">Save request</button>
            </form>
            @if($lead->reviewer)
                <p class="mt-4 text-xs text-slate-500">Last updated by {{ $lead->reviewer->name }}</p>
            @endif
        </section>
    </div>
</div>
@endsection
