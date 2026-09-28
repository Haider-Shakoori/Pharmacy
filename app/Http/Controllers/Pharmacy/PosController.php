<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StorePosSaleRequest;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\StockLocation;
use App\Services\Sales\PosSaleService;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        return view('pharmacy.pos.index', [
            'locations' => StockLocation::query()->with('branch:id,name')->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(),
            'recentSales' => Sale::query()->with('customer:id,name')->where('status', 'completed')->latest('completed_at')->limit(10)->get(),
        ]);
    }

    public function invoices(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $from = (string) $request->query('from', today()->subDays(30)->toDateString());
        $to = (string) $request->query('to', today()->toDateString());
        $paymentStatus = (string) $request->query('payment_status');

        $query = Sale::query()
            ->with(['customer:id,name,phone', 'cashier:id,name', 'location:id,name'])
            ->where('status', 'completed')
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('sale_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))))
            ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus));

        return view('pharmacy.pos.invoices', [
            'sales' => $query->clone()->latest('completed_at')->paginate(config('pharmacy.performance.default_page_size'))->withQueryString(),
            'summary' => [
                'count' => $query->clone()->count(),
                'total' => (string) ($query->clone()->sum('grand_total') ?? 0),
                'due' => (string) ($query->clone()->sum('due_total') ?? 0),
            ],
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'paymentStatus' => $paymentStatus,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'exists:stock_locations,id'],
        ]);

        $search = trim($validated['q']);
        $locationId = $validated['location'];

        $medicines = Medicine::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->where('barcode', $search)
                ->orWhere('medicine_code', 'like', "%{$search}%")
                ->orWhere('brand_name', 'like', "%{$search}%")
                ->orWhere('generic_name', 'like', "%{$search}%")
                ->orWhere('strength', 'like', "%{$search}%"))
            ->whereHas('batches', fn ($query) => $query
                ->where('stock_location_id', $locationId)
                ->where('status', 'active')
                ->where('available_quantity', '>', 0)
                ->whereNotNull('sale_price')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today())))
            ->with(['batches' => fn ($query) => $query
                ->where('stock_location_id', $locationId)
                ->where('status', 'active')
                ->where('available_quantity', '>', 0)
                ->whereNotNull('sale_price')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('created_at')])
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $medicines->map(function (Medicine $medicine): array {
                $stock = $medicine->batches->reduce(
                    fn (BigDecimal $carry, $batch) => $carry->plus(BigDecimal::of($batch->available_quantity)),
                    BigDecimal::zero(),
                );

                $batchPrices = $medicine->batches->pluck('sale_price')->filter()->map(fn ($price) => (float) $price);

                return [
                    'id' => $medicine->id,
                    'code' => $medicine->medicine_code,
                    'barcode' => $medicine->barcode,
                    'name' => $medicine->brand_name,
                    'generic_name' => $medicine->generic_name,
                    'strength' => $medicine->strength,
                    'sale_unit' => $medicine->sale_unit,
                    'price' => $medicine->batches->first()?->sale_price,
                    'price_min' => $batchPrices->min(),
                    'price_max' => $batchPrices->max(),
                    'available' => (string) $stock,
                    'prescription_required' => $medicine->prescription_required,
                    'batches' => $medicine->batches->map(fn ($batch) => [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'available' => (string) $batch->available_quantity,
                        'sale_price' => (string) $batch->sale_price,
                        'expires_at' => $batch->expires_at?->toDateString(),
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    public function store(StorePosSaleRequest $request, PosSaleService $sales): JsonResponse
    {
        $sale = $sales->checkout($request->user(), $request->validated());

        return response()->json([
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'receipt_url' => route('pharmacy.pos.receipt', $sale),
        ], 201);
    }

    public function receipt(Sale $sale): View
    {
        abort_unless($sale->status === 'completed', 404);

        return view('pharmacy.pos.receipt', [
            'sale' => $sale->load(['lines.allocations.batch', 'lines.returnLines.saleReturn', 'payments', 'customer', 'location.branch', 'cashier:id,name']),
        ]);
    }
}
