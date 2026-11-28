<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\JournalLine;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatementController extends Controller
{
    public function customer(Request $request, Customer $customer): View
    {
        return $this->statement($request, $customer, 'accounts_receivable', false);
    }

    public function supplier(Request $request, Supplier $supplier): View
    {
        return $this->statement($request, $supplier, 'accounts_payable', true);
    }

    public function customerReceipt(Customer $customer, \App\Models\CustomerSettlement $payment): View
    {
        abort_unless($customer->sales()->whereKey($payment->sale_id)->exists(), 404);
        return view('pharmacy.accounts.receipt', ['party'=>$customer,'payment'=>$payment,'supplier'=>false]);
    }

    public function supplierReceipt(Supplier $supplier, \App\Models\SupplierPayment $payment): View
    {
        abort_unless((string)$payment->supplier_id === (string)$supplier->id, 404);
        return view('pharmacy.accounts.receipt', ['party'=>$supplier,'payment'=>$payment,'supplier'=>true]);
    }

    public function customerBulk(Request $request, Customer $customer, \App\Services\Accounting\AllocateAccountPayment $allocator): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()->hasPermission('pos.sell'),403);
        return $this->bulk($request,$customer,$allocator,true);
    }

    public function supplierBulk(Request $request, Supplier $supplier, \App\Services\Accounting\AllocateAccountPayment $allocator): \Illuminate\Http\RedirectResponse
    {
        return $this->bulk($request,$supplier,$allocator,false);
    }

    private function bulk(Request $request, Customer|Supplier $party, \App\Services\Accounting\AllocateAccountPayment $allocator, bool $customer): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'amount'=>['required','numeric','gt:0','decimal:0,4','max:999999999999'],
            'currency'=>['required','alpha','size:3'],
            'method'=>['required',$customer?'in:cash,bank,mobile':'in:cash,bank,hawala,other'],
            'reference'=>['nullable','string','max:160'],
            'idempotency_key'=>['required','uuid'],
        ]);
        $data['currency'] = strtoupper($data['currency']);
        $allocator->record($party,$data,$request->user()->id);
        return back()->with('success',__('accounts.payment_recorded'));
    }

    public function reverseCustomer(Request $request, Customer $customer, \App\Models\CustomerSettlement $payment, \App\Services\Accounting\ReverseAccountPayment $service): \Illuminate\Http\RedirectResponse
    {
        abort_unless($customer->sales()->whereKey($payment->sale_id)->exists(),404);
        $service->reverse($payment,$request->validate(['reason'=>'required|string|max:500'])['reason'],$request->user()->id);
        return back();
    }

    public function reverseSupplier(Request $request, Supplier $supplier, \App\Models\SupplierPayment $payment, \App\Services\Accounting\ReverseAccountPayment $service): \Illuminate\Http\RedirectResponse
    {
        abort_unless((string)$payment->supplier_id === (string)$supplier->id,404);
        $service->reverse($payment,$request->validate(['reason'=>'required|string|max:500'])['reason'],$request->user()->id);
        return back();
    }

    private function statement(Request $request, Customer|Supplier $party, string $key, bool $payable): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $lines = JournalLine::query()->with('entry')
            ->where('counterparty_type', $party::class)
            ->where('counterparty_id', $party->id)
            ->whereHas('account', fn ($q) => $q->where('system_key', $key))
            ->whereHas('entry', fn ($q) => $q->whereIn('status', ['posted', 'reversed'])->where('currency', 'AFN'))
            ->get()->sortBy(fn ($line) => $line->entry->business_date->toDateString().' '.$line->entry->occurred_at.' '.$line->id);
        $opening = BigDecimal::zero();
        $balance = BigDecimal::zero();
        $rows = [];
        foreach ($lines as $line) {
            $date = $line->entry->business_date->toDateString();
            if (!empty($filters['to']) && $date > $filters['to']) continue;
            $delta = BigDecimal::of($line->debit)->minus($line->credit);
            if ($payable) $delta = $delta->negated();
            $balance = $balance->plus($delta);
            if (!empty($filters['from']) && $date < $filters['from']) {
                $opening = $balance;
                continue;
            }
            $rows[] = ['line' => $line, 'balance' => (string) $balance];
        }
        return view('pharmacy.accounts.statement', compact('party', 'rows', 'opening', 'balance', 'filters'));
    }
}
