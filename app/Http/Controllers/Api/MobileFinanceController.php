<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\SafeMovement;
use App\Models\SupplierPayment;
use App\Services\Mobile\MobileAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileFinanceController extends Controller
{
    public function index(Request $request, MobileAccessService $access): JsonResponse
    {
        $context = $access->authenticate($request);
        abort_unless(in_array('accounting.manage', $context->permissions, true), 403);

        return $context->tenant->run(function (): JsonResponse {
            $expenses = Expense::query()
                ->latest('business_date')
                ->limit(30)
                ->get(['id', 'expense_number', 'business_date', 'currency', 'amount', 'payee', 'status']);

            $supplierPayments = SupplierPayment::query()
                ->latest('paid_at')
                ->limit(30)
                ->get(['id', 'payment_number', 'paid_at', 'currency', 'amount', 'supplier_id', 'method']);

            $cashMovements = SafeMovement::query()
                ->latest('occurred_at')
                ->limit(30)
                ->get(['id', 'cash_safe_id', 'business_date', 'direction', 'movement_type', 'amount', 'occurred_at']);

            return response()->json([
                'data' => [
                    'expenses' => $expenses,
                    'supplier_payments' => $supplierPayments,
                    'cash_movements' => $cashMovements,
                ],
            ]);
        });
    }
}
