<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\DailyClosing;
use App\Models\StockLocation;
use App\Services\DailyClosing\DailyClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyClosingController extends Controller
{
    public function index(Request $request, DailyClosingService $service): View
    {
        $locations = StockLocation::query()->with('branch:id,name')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $location = $locations->firstWhere('id', $request->query('location')) ?? $locations->first();
        $date = $service->businessDate();

        return view('pharmacy.daily-closing.index', [
            'locations' => $locations,
            'location' => $location,
            'businessDate' => $date,
            'snapshot' => $location ? $service->snapshot($location, $date) : null,
            'closing' => $location ? DailyClosing::query()->with(['events' => fn ($query) => $query->latest('occurred_at')])->where('stock_location_id', $location->id)->where('business_date', $date)->first() : null,
            'openShift' => $location ? CashierShift::query()->where('stock_location_id', $location->id)->where('user_id', $request->user()->id)->where('status', 'open')->first() : null,
            'shifts' => $location ? CashierShift::query()->with('user:id,name')->where('stock_location_id', $location->id)->where('business_date', $date)->latest('opened_at')->get() : collect(),
        ]);
    }

    public function openShift(Request $request, DailyClosingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'stock_location_id' => ['required', 'exists:stock_locations,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $service->openShift($request->user(), StockLocation::query()->findOrFail($validated['stock_location_id']), (string) $validated['opening_cash']);

        return back()->with('success', 'Cashier shift opened.');
    }

    public function closeShift(Request $request, CashierShift $cashierShift, DailyClosingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->closeShift($cashierShift, $request->user(), (string) $validated['counted_cash'], $validated['notes'] ?? null);

        return back()->with('success', 'Cashier shift closed.');
    }

    public function finalize(Request $request, DailyClosingService $service): RedirectResponse
    {
        $validated = $request->validate([
            'stock_location_id' => ['required', 'exists:stock_locations,id'],
            'counted_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->finalize(
            StockLocation::query()->findOrFail($validated['stock_location_id']),
            $request->user(),
            isset($validated['counted_cash']) ? (string) $validated['counted_cash'] : null,
            $validated['notes'] ?? null,
        );

        return back()->with('success', 'Daily Closing finalized.');
    }

    public function approve(DailyClosing $dailyClosing, Request $request, DailyClosingService $service): RedirectResponse
    {
        $service->approve($dailyClosing, $request->user());

        return back()->with('success', 'Daily Closing approved.');
    }

    public function reopen(DailyClosing $dailyClosing, Request $request, DailyClosingService $service): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->reopen($dailyClosing, $request->user(), $validated['reason']);

        return back()->with('success', 'Daily Closing reopened with audit history preserved.');
    }
}
