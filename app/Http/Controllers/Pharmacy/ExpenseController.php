<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\ReverseAccountingSourceRequest;
use App\Http\Requests\Pharmacy\StoreExpenseRequest;
use App\Models\Expense;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\RedirectResponse;

class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $request, ExpenseService $service): RedirectResponse
    {
        $expense = $service->post($request->validated(), $request->user()->id);

        return back()->with('success', "Expense {$expense->expense_number} posted.");
    }

    public function reverse(
        ReverseAccountingSourceRequest $request,
        Expense $expense,
        ExpenseService $service,
    ): RedirectResponse {
        $service->reverse($expense, $request->validated('reason'), $request->user()->id);

        return back()->with('success', "Expense {$expense->expense_number} reversed with an audit journal.");
    }
}
