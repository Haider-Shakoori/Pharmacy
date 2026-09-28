<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\ReverseAccountingSourceRequest;
use App\Http\Requests\Pharmacy\StoreAccountingAdjustmentRequest;
use App\Models\AccountingAdjustment;
use App\Services\Accounting\AccountingAdjustmentService;
use Illuminate\Http\RedirectResponse;

class AccountingAdjustmentController extends Controller
{
    public function store(StoreAccountingAdjustmentRequest $request, AccountingAdjustmentService $service): RedirectResponse
    {
        $adjustment = $service->post($request->validated(), $request->user()->id);

        return back()->with('success', "Adjustment {$adjustment->adjustment_number} posted.");
    }

    public function reverse(
        ReverseAccountingSourceRequest $request,
        AccountingAdjustment $accountingAdjustment,
        AccountingAdjustmentService $service,
    ): RedirectResponse {
        $service->reverse($accountingAdjustment, $request->validated('reason'), $request->user()->id);

        return back()->with('success', "Adjustment {$accountingAdjustment->adjustment_number} reversed with an audit journal.");
    }
}
