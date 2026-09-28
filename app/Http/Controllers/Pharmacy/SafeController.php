<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\CashSafe;
use App\Models\SafeClosing;
use App\Models\StockLocation;
use App\Services\Safe\SafeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SafeController extends Controller
{
    public function index(Request $request, SafeService $service): View
    {
        $service->ensureDefaultSafe();
        $safes = CashSafe::query()->with('location.branch:id,name')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $safe = $safes->firstWhere('id', $request->query('safe')) ?? $safes->first();
        $date = $service->businessDate();

        return view('pharmacy.safe.index', [
            'safes' => $safes,
            'safe' => $safe,
            'locations' => StockLocation::query()->with('branch:id,name')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'businessDate' => $date,
            'balance' => $safe ? $service->balance($safe) : '0.0000',
            'snapshot' => $safe ? $service->snapshot($safe, $date) : null,
            'pendingShifts' => $safe ? $service->pendingShiftTransfers($safe, $date) : collect(),
            'closing' => $safe ? SafeClosing::query()->with(['events.actor:id,name'])->where('cash_safe_id', $safe->id)->whereDate('business_date', $date)->first() : null,
            'movements' => $safe ? $safe->movements()->with('creator:id,name')->latest('occurred_at')->limit(30)->get() : collect(),
            'recentClosings' => $safe ? $safe->closings()->latest('business_date')->limit(14)->get() : collect(),
        ]);
    }

    public function storeSafe(Request $request): RedirectResponse
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);
        $validated = $request->validate([
            'stock_location_id' => ['required', 'exists:stock_locations,id'],
            'code' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('cash_safes', 'code')],
            'name' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $safe = CashSafe::query()->create([
            ...$validated,
            'code' => Str::upper($validated['code']),
            'is_default' => false,
            'is_active' => true,
        ]);

        return redirect()->route('pharmacy.safe.index', ['safe' => $safe->id])->with('success', __('safe.messages.created'));
    }

    public function movement(CashSafe $cashSafe, Request $request, SafeService $service): RedirectResponse
    {
        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(SafeService::MANUAL_TYPES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:160'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);

        $service->postManual($cashSafe, $request->user(), $validated);

        return redirect()->route('pharmacy.safe.index', ['safe' => $cashSafe->id])->with('success', __('safe.messages.movement_posted'));
    }

    public function receiveShift(CashSafe $cashSafe, CashierShift $cashierShift, Request $request, SafeService $service): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);

        $service->receiveFromShift($cashSafe, $cashierShift, $request->user(), (string) $validated['amount'], $validated['idempotency_key']);

        return redirect()->route('pharmacy.safe.index', ['safe' => $cashSafe->id])->with('success', __('safe.messages.pos_received'));
    }

    public function finalize(CashSafe $cashSafe, Request $request, SafeService $service): RedirectResponse
    {
        $validated = $request->validate([
            'counted_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->finalize($cashSafe, $request->user(), (string) $validated['counted_balance'], $validated['notes'] ?? null);

        return redirect()->route('pharmacy.safe.index', ['safe' => $cashSafe->id])->with('success', __('safe.messages.closed'));
    }

    public function approve(SafeClosing $safeClosing, Request $request, SafeService $service): RedirectResponse
    {
        $service->approve($safeClosing, $request->user());

        return redirect()->route('pharmacy.safe.index', ['safe' => $safeClosing->cash_safe_id])->with('success', __('safe.messages.approved'));
    }

    public function reopen(SafeClosing $safeClosing, Request $request, SafeService $service): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->reopen($safeClosing, $request->user(), $validated['reason']);

        return redirect()->route('pharmacy.safe.index', ['safe' => $safeClosing->cash_safe_id])->with('success', __('safe.messages.reopened'));
    }
}
