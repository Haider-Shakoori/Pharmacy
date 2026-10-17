<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Marketing\WebsiteAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class WebsiteAnalyticsController extends Controller
{
    public function index(WebsiteAnalyticsService $analytics): View
    {
        return view('platform.analytics.index', ['report' => $analytics->snapshot()]);
    }

    public function storeMention(Request $request, WebsiteAnalyticsService $analytics): RedirectResponse
    {
        $data = $request->validate([
            'engine' => ['required', Rule::in(['ChatGPT', 'Perplexity', 'Gemini', 'Copilot', 'Claude', 'Other'])],
            'question' => ['required', 'string', 'min:12', 'max:250'],
            'result' => ['required', Rule::in(['mentioned_with_link', 'mentioned_without_link', 'not_mentioned', 'unclear'])],
            'evidence_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
        ]);

        try {
            $analytics->recordMention(
                $data['engine'],
                trim($data['question']),
                $data['result'],
                $data['evidence_url'] ?? '',
            );
        } catch (RuntimeException $e) {
            return back()->with('error', 'Website analytics storage is not available. No observation was saved.');
        }

        return back()->with('success', 'Observed AI response recorded.')->withFragment('ai-checks');
    }
}
