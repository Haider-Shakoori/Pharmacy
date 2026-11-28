<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreCustomerRequest;
use App\Http\Requests\Pharmacy\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        return view('pharmacy.customers.index', [
            'customers' => Customer::query()
                ->withCount(['sales' => fn ($query) => $query->where('status', 'completed')])
                ->withSum(['sales as outstanding_credit' => fn ($query) => $query->where('status', 'completed')], 'due_total')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate(config('pharmacy.performance.default_page_size'))
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('pharmacy.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated());

        return redirect()->route('pharmacy.customers.show', $customer)->with('success', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        $sales = $customer->sales()->where('status', 'completed')->latest('completed_at')->paginate(20);

        return view('pharmacy.customers.show', [
            'customer' => $customer,
            'settlements' => \App\Models\CustomerSettlement::query()->with("paymentJournal")->whereIn('sale_id', $customer->sales()->select('id'))->latest('paid_at')->paginate(20, ['*'], 'payments_page'),
            'outstandingSales' => $customer->sales()->where('status', 'completed')->where('due_total', '>', 0)->orderBy('business_date')->get(),
            'sales' => $sales,
            'salesTotal' => (string) $customer->sales()->where('status', 'completed')->sum('grand_total'),
            'outstandingCredit' => (string) $customer->sales()->where('status', 'completed')->sum('due_total'),
        ]);
    }

    public function receivePayment(Request $request, Customer $customer, \App\Services\Sales\RecordCustomerSettlement $recorder): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('pos.sell'), 403);
        $data = $request->validate([
            'sale_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4', 'max:999999999999'],
            'method' => ['required', 'in:cash,bank,mobile'],
            'reference' => ['nullable', 'string', 'max:160'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $saleId = $data['sale_id'];
        unset($data['sale_id']);
        $payment = $recorder->record($customer, $saleId, $data, $request->user()->id);
        if ($request->boolean('print')) return redirect()->route('pharmacy.customers.payments.receipt', [$customer,$payment,'print'=>1]);
        return back()->with('success', __('accounts.payment_recorded'));
    }

    public function edit(Customer $customer): View
    {
        return view('pharmacy.customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return back()->with('success', 'Customer updated.');
    }
}
