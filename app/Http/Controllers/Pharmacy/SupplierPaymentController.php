<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreSupplierPaymentRequest;
use App\Models\PurchaseInvoice;
use App\Services\Purchasing\RecordSupplierPayment;
use Illuminate\Http\RedirectResponse;

class SupplierPaymentController extends Controller
{
    public function store(
        StoreSupplierPaymentRequest $request,
        PurchaseInvoice $purchaseInvoice,
        RecordSupplierPayment $recorder,
    ): RedirectResponse {
        $payment = $recorder->record(
            $purchaseInvoice,
            [
                ...$request->validated(),
                'currency' => strtoupper($request->validated('currency')),
            ],
            $request->user()->id,
        );

        if ($request->boolean('print')) {
            return redirect()->route('pharmacy.suppliers.payments.receipt', [$purchaseInvoice->supplier_id, $payment, 'print' => 1]);
        }

        return back()->with('success', "Supplier payment {$payment->payment_number} recorded.");
    }
}
