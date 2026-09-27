<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StorePurchaseInvoiceRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceController extends Controller
{
    public function store(StorePurchaseInvoiceRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->status === 'approved', 422, 'Only approved purchase orders can be invoiced.');
        abort_if($purchaseOrder->invoices()->where('status', '!=', 'cancelled')->exists(), 422, 'This purchase order already has an active invoice.');

        $data = $request->validated();

        if (! empty($data['goods_receipt_id'])) {
            $receipt = GoodsReceipt::query()
                ->whereKey($data['goods_receipt_id'])
                ->where('purchase_order_id', $purchaseOrder->id)
                ->first();

            if (! $receipt) {
                throw ValidationException::withMessages(['goods_receipt_id' => 'The selected goods receipt does not belong to this purchase order.']);
            }
        }

        if (! empty($data['supplier_invoice_number']) && PurchaseInvoice::query()
            ->where('supplier_id', $purchaseOrder->supplier_id)
            ->where('supplier_invoice_number', $data['supplier_invoice_number'])
            ->exists()) {
            throw ValidationException::withMessages(['supplier_invoice_number' => 'This supplier invoice number has already been recorded.']);
        }

        $invoice = DB::transaction(function () use ($data, $purchaseOrder, $request): PurchaseInvoice {
            $dueDate = $data['due_date'] ?? Carbon::parse($data['invoice_date'])
                ->addDays($purchaseOrder->supplier->payment_terms_days)
                ->toDateString();

            return PurchaseInvoice::query()->create([
                'supplier_id' => $purchaseOrder->supplier_id,
                'purchase_order_id' => $purchaseOrder->id,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'invoice_number' => 'PINV-'.now()->format('Ymd').'-'.Str::upper(Str::ulid()->toBase32()),
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $dueDate,
                'currency' => $purchaseOrder->currency,
                'status' => 'open',
                'subtotal' => $purchaseOrder->subtotal,
                'discount_total' => $purchaseOrder->discount_total,
                'landed_cost_total' => $purchaseOrder->landed_cost_total,
                'grand_total' => $purchaseOrder->grand_total,
                'paid_total' => '0',
                'balance_due' => $purchaseOrder->grand_total,
                'created_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()->route('pharmacy.purchase-orders.show', $purchaseOrder)
            ->with('success', "Supplier invoice {$invoice->invoice_number} recorded.");
    }
}
