@extends('layouts.platform')

@section('title', 'Website Analytics — Darmaltoon Platform')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-bold text-teal-700">Platform owner · Marketing website</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Website Analytics</h2>
            <p class="mt-2 text-sm text-slate-600">Darmaltoon.com traffic, conversions, and AI search visibility. Last 30 days (UTC).</p>
        </div>
        <a href="https://darmaltoon.com/" rel="noopener noreferrer" target="_blank"
           class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">View public website ↗</a>
    </div>

    @unless ($report['available'])
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
            <h3 class="font-bold">Marketing analytics data is not available</h3>
            <p class="mt-1">No private metrics database could be read. No data has been reset or recreated. The platform administrator should verify the analytics storage path and permissions.</p>
        </section>
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Website metrics">
            @foreach ([
                ['Page views', $report['metrics']['views']],
                ['AI-attributed visits', $report['metrics']['ai_referrals']],
                ['Demo requests received', $report['metrics']['demo_requests']],
                ['Contact requests received', $report['metrics']['contact_requests']],
            ] as [$label, $value])
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
                    <p class="mt-3 text-3xl font-black text-slate-900">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach ([
                'Traffic sources' => $report['sources'],
                'AI assistant referrals' => $report['ai_providers'],
                'Top website pages' => $report['pages'],
                'Marketing campaigns' => $report['campaigns'],
            ] as $title => $rows)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 class="mb-3 text-lg font-bold text-slate-900">{{ $title }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-100 text-slate-500">
                                <tr><th class="py-3 text-start font-semibold">Source / page</th><th class="py-3 text-end font-semibold">Views</th></tr>
                            </thead>
                            <tbody>
                            @forelse ($rows as $row)
                                <tr class="border-b border-slate-50">
                                    <td class="max-w-xs break-words py-3 text-slate-700">{{ $row['label'] ?: 'Unknown' }}</td>
                                    <td class="py-3 text-end font-semibold tabular-nums">{{ number_format($row['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="py-4 text-slate-500">No recorded data yet</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>

        <section id="ai-checks" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="mb-5 space-y-2">
                <p class="text-xs font-bold uppercase tracking-wider text-teal-700">AI search visibility</p>
                <h3 class="text-xl font-bold">Verified AI mentions</h3>
                <p class="text-sm leading-6 text-slate-600">AI-attributed visits are referral signals, not AI recommendations or search impressions. Responses that never send a visit cannot be detected by website analytics. Record only answers you have personally checked, including non-mentions.</p>
            </div>
            <div class="mb-5 flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full bg-teal-50 px-3 py-2 text-teal-800">Mentions with links: {{ $report['mention_totals']['mentioned_with_link'] ?? 0 }}</span>
                <span class="rounded-full bg-blue-50 px-3 py-2 text-blue-800">Mentions without links: {{ $report['mention_totals']['mentioned_without_link'] ?? 0 }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">Not mentioned: {{ $report['mention_totals']['not_mentioned'] ?? 0 }}</span>
            </div>

            <form method="POST" action="{{ route('platform.analytics.mentions.store') }}" class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label for="engine" class="block text-sm font-semibold">Assistant reviewed</label>
                    <select id="engine" name="engine" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm">
                        @foreach (['ChatGPT', 'Perplexity', 'Gemini', 'Copilot', 'Claude', 'Other'] as $engine)
                            <option value="{{ $engine }}" @selected(old('engine') === $engine)>{{ $engine }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="result" class="block text-sm font-semibold">Observed result</label>
                    <select id="result" name="result" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm">
                        @foreach ([
                            'mentioned_with_link' => 'Mentioned with a link',
                            'mentioned_without_link' => 'Mentioned without a link',
                            'not_mentioned' => 'Not mentioned',
                            'unclear' => 'Unclear',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('result') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="question" class="block text-sm font-semibold">Exact AI question tested</label>
                    <textarea id="question" name="question" rows="2" required minlength="12" maxlength="250"
                              placeholder="Which pharmacy management systems offer medicine expiry tracking?" class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">{{ old('question') }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label for="evidence_url" class="block text-sm font-semibold">Evidence URL (optional, HTTPS)</label>
                    <input id="evidence_url" name="evidence_url" type="url" maxlength="500" value="{{ old('evidence_url') }}"
                           placeholder="https://..." class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white hover:bg-teal-800">Save verified observation</button>
                </div>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-3 text-start">Checked (UTC)</th><th class="px-3 py-3 text-start">Assistant</th>
                            <th class="px-3 py-3 text-start">Prompt</th><th class="px-3 py-3 text-start">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['mentions'] as $check)
                            <tr class="border-b border-slate-100 align-top">
                                <td class="whitespace-nowrap px-3 py-3">{{ $check['checked_at'] }}</td>
                                <td class="px-3 py-3">{{ $check['engine'] }}</td>
                                <td class="max-w-md break-words px-3 py-3">{{ $check['question'] }}</td>
                                <td class="px-3 py-3">{{ str_replace('_', ' ', $check['result']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-5 text-slate-500">No AI answer checks have been recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endunless

    <p class="text-xs leading-5 text-slate-500">This is marketing website data, not customer pharmacy activity. Counts are aggregate page events—not unique visitors, Google Search impressions, or automatically verified AI mentions. Data is restricted to platform administrators.</p>
</div>
@endsection

