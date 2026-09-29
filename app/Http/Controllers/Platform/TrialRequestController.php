<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\TrialRequest;
use App\Services\Subscriptions\TrialRequestApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TrialRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status'));

        $trialRequests = TrialRequest::query()
            ->with(['tenant.business', 'tenant.domains', 'reviewer'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'failed' THEN 1 WHEN 'provisioning' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate(config('pharmacy.performance.default_page_size'))
            ->withQueryString();

        return view('platform.trial-requests.index', compact('trialRequests', 'status'));
    }

    public function approve(
        TrialRequest $trialRequest,
        TrialRequestApprovalService $approvals,
    ): RedirectResponse {
        /** @var PlatformAdmin $reviewer */
        $reviewer = request()->user('platform');

        try {
            $result = $approvals->approve($trialRequest, $reviewer);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (Throwable $exception) {
            return back()->with(
                'error',
                'Approval was recorded but provisioning could not finish: '.str($exception->getMessage())->limit(240),
            );
        }

        $response = redirect()
            ->route('platform.tenants.edit', $result['tenant']);

        if ($result['request']->status === 'approved') {
            $response->with(
                'success',
                'Trial request approved. The pharmacy account is ready and its 7-day trial is active.',
            );
        } else {
            $response->with(
                'success',
                'Trial request approved. Provisioning is waiting for domain/TLS readiness; retry provisioning before starting the trial.',
            );
        }

        if ($result['license_key'] !== null) {
            $response->with('generated_license_key', $result['license_key']);
        }

        return $response;
    }

    public function reject(Request $request, TrialRequest $trialRequest): RedirectResponse
    {
        if ($trialRequest->status === 'approved') {
            throw ValidationException::withMessages([
                'decision' => 'An approved request cannot be rejected.',
            ]);
        }

        $validated = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var PlatformAdmin $reviewer */
        $reviewer = $request->user('platform');

        $trialRequest->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'decision_notes' => $validated['decision_notes'] ?? null,
            'failure_reason' => null,
            'owner_password_ciphertext' => null,
        ])->save();

        return back()->with('success', 'Trial request rejected.');
    }
}
