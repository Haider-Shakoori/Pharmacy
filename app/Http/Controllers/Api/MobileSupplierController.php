<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\Mobile\MobileAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSupplierController extends Controller
{
    public function index(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('inventory.manage', $context->permissions, true), 403);

        return $context->tenant->run(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'search' => ['sometimes', 'string', 'max:100'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);
            $search = trim((string) ($validated['search'] ?? ''));
            $limit = (int) ($validated['limit'] ?? 50);

            $suppliers = Supplier::query()
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                ))
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'code', 'name', 'contact_person', 'phone', 'city', 'province', 'is_active']);

            return response()->json(['data' => $suppliers]);
        });
    }

    public function show(Request $request, MobileAccessService $access, string $supplier): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('inventory.manage', $context->permissions, true), 403);

        return $context->tenant->run(function () use ($context, $supplier): JsonResponse {
            $record = Supplier::query()->findOrFail($supplier);
            $orders = $record->purchaseOrders()
                ->latest('order_date')
                ->limit(20)
                ->get(['id', 'number', 'status', 'order_date', 'currency', 'grand_total']);

            $response = [
                'supplier' => $record->only([
                    'id', 'code', 'name', 'contact_person', 'phone', 'whatsapp',
                    'email', 'address', 'city', 'province', 'payment_terms_days',
                    'is_active', 'notes',
                ]),
                'purchase_orders' => $orders,
            ];

            // Financial details require an independent accounting permission.
            if (in_array('accounting.manage', $context->permissions, true)) {
                $response['invoices'] = $record->invoices()
                    ->latest('invoice_date')
                    ->limit(30)
                    ->get(['id', 'invoice_number', 'status', 'invoice_date',
                        'due_date', 'currency', 'grand_total', 'paid_total', 'balance_due']);

                $response['outstanding_by_currency'] = $record->invoices()
                    ->selectRaw('currency, SUM(balance_due) as amount')
                    ->groupBy('currency')
                    ->get()
                    ->map(fn ($row) => [
                        'currency' => $row->currency,
                        'amount' => (string) $row->amount,
                    ]);

                $response['payments'] = \App\Models\SupplierPayment::query()
                    ->where('supplier_id', $record->id)
                    ->latest('paid_at')
                    ->limit(30)
                    ->get(['id', 'payment_number', 'amount', 'currency',
                        'method', 'paid_at', 'purchase_invoice_id']);
            }

            return response()->json(['data' => $response]);
        });
    }
}
