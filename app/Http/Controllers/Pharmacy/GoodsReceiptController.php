<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function create(PurchaseOrder $purchaseOrder): View
    {
        abort_unless($purchaseOrder->status === 'approved', 422, 'Only approved purchase orders can receive goods.');

        return view('pharmacy.purchasing.receipts.create', [
            'order' => $purchaseOrder->load(['supplier', 'lines.medicine']),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->status === 'approved', 422, 'Only approved purchase orders can receive goods.');

        $data = $request->validated();

        if (! empty($data['idempotency_key'])) {
            $existing = GoodsReceipt::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->purchase_order_id === $purchaseOrder->id, 409, 'Idempotency key already belongs to another purchase order.');

                return redirect()->route('pharmacy.purchase-orders.show', $purchaseOrder)
                    ->with('success', "Goods receipt {$existing->receipt_number} was already captured.");
            }
        }

        $receiptLines = collect($data['lines'])
            ->filter(fn (array $line) => BigDecimal::of((string) $line['received_quantity'])->isGreaterThan(BigDecimal::zero()))
            ->values();

        if ($receiptLines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Enter a received quantity for at least one purchase-order line.']);
        }

        $receipt = DB::transaction(function () use ($data, $receiptLines, $purchaseOrder, $request): GoodsReceipt {
            $orderLines = $purchaseOrder->lines()->with('medicine')->get()->keyBy('id');

            foreach ($receiptLines as $line) {
                $orderLine = $orderLines->get($line['purchase_order_line_id']);
                if (! $orderLine) {
                    throw ValidationException::withMessages(['lines' => 'A receipt line does not belong to this purchase order.']);
                }

                $pending = BigDecimal::of((string) $orderLine->received_quantity)
                    ->plus((string) GoodsReceipt::query()
                        ->where('purchase_order_id', $purchaseOrder->id)
                        ->whereNull('inventory_posted_at')
                        ->join('goods_receipt_lines', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
                        ->where('goods_receipt_lines.purchase_order_line_id', $orderLine->id)
                        ->sum('goods_receipt_lines.received_quantity'));

                if ($pending->plus((string) $line['received_quantity'])->isGreaterThan(BigDecimal::of($orderLine->ordered_quantity))) {
                    throw ValidationException::withMessages(['lines' => 'Received quantity exceeds the remaining ordered quantity.']);
                }

                $medicine = $orderLine->medicine;
                if ($medicine->batch_tracking_required && blank($line['batch_number'] ?? null)) {
                    throw ValidationException::withMessages(['lines' => "{$medicine->brand_name} requires a batch number."]);
                }
                if ($medicine->expiry_tracking_required && blank($line['expires_at'] ?? null)) {
                    throw ValidationException::withMessages(['lines' => "{$medicine->brand_name} requires an expiry date."]);
                }
            }

            $receipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'receipt_number' => 'GRN-'.now()->format('Ymd').'-'.Str::upper(Str::ulid()->toBase32()),
                'status' => 'pending_inventory',
                'received_at' => $data['received_at'],
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($receiptLines as $line) {
                $orderLine = $orderLines->get($line['purchase_order_line_id']);
                $receipt->lines()->create([
                    'purchase_order_line_id' => $orderLine->id,
                    'medicine_id' => $orderLine->medicine_id,
                    'received_quantity' => $line['received_quantity'],
                    'bonus_quantity' => $line['bonus_quantity'] ?? '0',
                    'batch_number' => $line['batch_number'] ?? null,
                    'manufactured_at' => $line['manufactured_at'] ?? null,
                    'expires_at' => $line['expires_at'] ?? null,
                    'unit_cost' => $line['unit_cost'],
                    'sale_price' => $line['sale_price'] ?? null,
                ]);
            }

            return $receipt;
        });

        return redirect()->route('pharmacy.purchase-orders.show', $purchaseOrder)
            ->with('success', "Goods receipt {$receipt->receipt_number} captured and is waiting for Batch 11 inventory posting.");
    }
}
