<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreSaleReturnRequest;
use App\Models\Sale;
use App\Services\Sales\SaleReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SaleReturnController extends Controller
{
    public function create(Sale $sale): View
    {
        abort_unless($sale->status === 'completed', 404);

        return view('pharmacy.pos.return', [
            'sale' => $sale->load([
                'lines.allocations.batch',
                'lines' => fn ($query) => $query->withSum([
                    'returnLines as returned_quantity' => fn ($query) => $query
                        ->whereHas('saleReturn', fn ($query) => $query->where('status', 'completed')),
                ], 'quantity'),
            ]),
        ]);
    }

    public function store(StoreSaleReturnRequest $request, Sale $sale, SaleReturnService $returns): RedirectResponse
    {
        $saleReturn = $returns->process($sale, $request->user(), $request->validated());

        return redirect()
            ->route('pharmacy.pos.receipt', $sale)
            ->with('success', "Return {$saleReturn->return_number} completed.");
    }
}
