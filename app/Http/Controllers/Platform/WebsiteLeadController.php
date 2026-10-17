<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\WebsiteLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteLeadController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $kind = (string) $request->query('kind', '');
        $search = trim((string) $request->query('search', ''));
        $test = (string) $request->query('test', '');

        $leads = WebsiteLead::query()
            ->when(in_array($status, WebsiteLead::STATUSES, true),
                fn ($query) => $query->where('status', $status))
            ->when(in_array($kind, ['demo', 'contact'], true),
                fn ($query) => $query->where('kind', $kind))
            ->when($test === 'yes', fn ($query) => $query->where('is_test', true))
            ->when($test !== 'yes', fn ($query) => $query->where('is_test', false))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                    $query->where('contact_name', 'like', $like)
                        ->orWhere('pharmacy', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderByDesc('submitted_at')
            ->paginate(20)->withQueryString();

        return view('platform.website-leads.index', [
            'leads' => $leads,
            'status' => $status,
            'kind' => $kind,
            'search' => $search,
            'test' => $test,
            'newCount' => WebsiteLead::query()->where('status', 'new')->where('is_test', false)->count(),
        ]);
    }

    public function show(WebsiteLead $websiteLead): View
    {
        return view('platform.website-leads.show', ['lead' => $websiteLead->load('reviewer')]);
    }

    public function update(Request $request, WebsiteLead $websiteLead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(WebsiteLead::STATUSES)],
            'is_test' => ['required', 'boolean'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $websiteLead->forceFill([
            'status' => $data['status'],
            'is_test' => (bool) $data['is_test'],
            'internal_notes' => $data['internal_notes'] ?? null,
            'follow_up_at' => $data['follow_up_at'] ?? null,
            'reviewed_by' => $request->user('platform')->id,
        ])->save();

        return redirect()->route('platform.demo-requests.show', $websiteLead)
            ->with('success', 'Request updated.');
    }
}
