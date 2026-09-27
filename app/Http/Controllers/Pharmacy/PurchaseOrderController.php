<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StorePurchaseOrderRequest;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseTotalsCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        return view('pharmacy.purchasing.orders.index', [
            'orders' => PurchaseOrder::query()
                ->with('supplier:id,name')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"))))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest('order_date')
                ->paginate(config('pharmacy.performance.default_page_size'))
                ->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('pharmacy.purchasing.orders.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'medicines' => Medicine::query()->where('is_active', true)->orderBy('brand_name')->get(['id', 'brand_name', 'generic_name', 'strength', 'purchase_unit']),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, PurchaseTotalsCalculator $calculator): RedirectResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data, $calculator, $request): PurchaseOrder {
            try {
                $totals = $calculator->calculate($data['lines']);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
            }

            $order = PurchaseOrder::query()->create([
                'supplier_id' => $data['supplier_id'],
                'number' => 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::ulid()->toBase32()),
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'currency' => strtoupper($data['currency']),
                ...$totals,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $medicines = Medicine::query()->whereIn('id', collect($data['lines'])->pluck('medicine_id'))->get()->keyBy('id');

            foreach ($data['lines'] as $line) {
                $medicine = $medicines->get($line['medicine_id']);
                $order->lines()->create([
                    'medicine_id' => $medicine->id,
                    'description' => trim($medicine->brand_name.' '.($medicine->strength ?? '')),
                    'ordered_quantity' => $line['ordered_quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'discount_amount' => $line['discount_amount'] ?? '0',
                    'landed_cost_allocated' => $line['landed_cost_allocated'] ?? '0',
                    'line_total' => $calculator->lineTotal($line),
                ]);
            }

            return $order;
        });

        return redirect()->route('pharmacy.purchase-orders.show', $order)->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'lines.medicine', 'receipts.lines.medicine', 'invoices.payments']);

        return view('pharmacy.purchasing.orders.show', [
            'order' => $purchaseOrder,
            'stockLocations' => StockLocation::query()
                ->with('branch')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function submit(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->status === 'draft', 422, 'Only draft purchase orders can be submitted.');

        $purchaseOrder->update(['status' => 'submitted', 'submitted_at' => now()]);

        return back()->with('success', 'Purchase order submitted for approval.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('purchases.approve'), 403);
        abort_unless($purchaseOrder->status === 'submitted', 422, 'Only submitted purchase orders can be approved.');

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Purchase order approved.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if($purchaseOrder->receipts()->exists(), 422, 'A purchase order with goods receipts cannot be cancelled.');
        abort_if(in_array($purchaseOrder->status, ['cancelled', 'closed'], true), 422);

        $purchaseOrder->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return back()->with('success', 'Purchase order cancelled.');
    }
}
