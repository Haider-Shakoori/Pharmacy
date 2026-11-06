<?php

namespace App\Services\Mobile;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileGoodsReceiptService
{
    /**
     * Capture a pending receipt only. Inventory is posted separately by the
     * existing authenticated inventory-posting workflow.
     */
    public function capture(User $user, array $payload, string $key): GoodsReceipt
    {
        Validator::make($payload, [
            'purchase_order_id' => ['required', 'ulid'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:250'],
            'lines.*.purchase_order_line_id' => ['required', 'ulid'],
            'lines.*.received_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.bonus_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:120'],
            'lines.*.manufactured_at' => ['nullable', 'date'],
            'lines.*.expires_at' => ['nullable', 'date'],
        ])->validate();

        return DB::transaction(function () use ($user, $payload, $key): GoodsReceipt {
            // Serialize receipts against the order to avoid concurrent over-receiving.
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($payload['purchase_order_id']);
            $existing = GoodsReceipt::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->purchase_order_id !== $order->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key already belongs to another order.']);
                }

                return $existing;
            }

            if (! in_array($order->status, ['approved', 'partially_received'], true)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Order is not receivable.']);
            }

            $orderLines = $order->lines()->with('medicine')->get()->keyBy('id');
            $seen = [];
            $receivedLines = [];
            foreach ($payload['lines'] as $line) {
                $id = $line['purchase_order_line_id'];
                if (isset($seen[$id])) {
                    throw ValidationException::withMessages(['lines' => 'Duplicate purchase order line.']);
                }
                $seen[$id] = true;
                $orderLine = $orderLines->get($id);
                if (! $orderLine || ! $orderLine->medicine) {
                    throw ValidationException::withMessages(['lines' => 'Line does not belong to this purchase order.']);
                }

                $quantity = BigDecimal::of((string) $line['received_quantity']);
                if (! $quantity->isGreaterThan(BigDecimal::zero())) {
                    continue;
                }
                $pending = GoodsReceipt::query()
                    ->where('goods_receipts.purchase_order_id', $order->id)
                    ->whereNull('goods_receipts.inventory_posted_at')
                    ->join('goods_receipt_lines', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
                    ->where('goods_receipt_lines.purchase_order_line_id', $id)
                    ->sum('goods_receipt_lines.received_quantity');
                $total = BigDecimal::of((string) $orderLine->received_quantity)
                    ->plus((string) $pending)->plus((string) $line['received_quantity']);
                if ($total->isGreaterThan(BigDecimal::of((string) $orderLine->ordered_quantity))) {
                    throw ValidationException::withMessages(['lines' => 'Receipt exceeds remaining ordered quantity.']);
                }

                if ($orderLine->medicine->batch_tracking_required && blank($line['batch_number'] ?? null)) {
                    throw ValidationException::withMessages(['lines' => 'Batch number is required.']);
                }
                if ($orderLine->medicine->expiry_tracking_required && blank($line['expires_at'] ?? null)) {
                    throw ValidationException::withMessages(['lines' => 'Expiry date is required.']);
                }
                $receivedLines[] = [$orderLine, $line];
            }
            if ($receivedLines === []) {
                throw ValidationException::withMessages(['lines' => 'At least one positive received quantity is required.']);
            }

            $receipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'receipt_number' => 'GRN-'.now()->format('Ymd').'-'.Str::upper(Str::ulid()->toBase32()),
                'status' => 'pending_inventory',
                'received_at' => $payload['received_at'],
                'idempotency_key' => $key,
                'created_by' => $user->id,
                'notes' => $payload['notes'] ?? 'Captured from mobile synchronization.',
            ]);
            foreach ($receivedLines as [$orderLine, $line]) {
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
    }
}
