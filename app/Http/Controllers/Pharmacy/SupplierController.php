<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreSupplierRequest;
use App\Http\Requests\Pharmacy\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('pharmacy.purchasing.suppliers.index', [
            'suppliers' => Supplier::query()
                ->withCount(['purchaseOrders', 'invoices'])
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate(config('pharmacy.performance.default_page_size'))
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('pharmacy.purchasing.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated());

        return redirect()->route('pharmacy.suppliers.edit', $supplier)->with('success', 'Supplier created.');
    }

    public function edit(Supplier $supplier): View
    {
        $outstandingInvoices = $supplier->invoices()->where('balance_due', '>', 0)->whereNotIn('status', ['cancelled', 'paid'])->orderBy('invoice_date')->get();

        $settlements = \App\Models\SupplierPayment::query()->with("paymentJournal")->where('supplier_id', $supplier->id)->latest('paid_at')->paginate(20, ['*'], 'payments_page');
        return view('pharmacy.purchasing.suppliers.edit', compact('supplier', 'outstandingInvoices', 'settlements'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', 'Supplier updated.');
    }
}
