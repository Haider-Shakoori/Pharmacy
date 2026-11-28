<?php
namespace App\Services\Accounting;
use App\Models\{CustomerSettlement,SupplierPayment,Sale,PurchaseInvoice,JournalEntry};
use App\Services\DailyClosing\DailyClosingService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseAccountPayment {
    public function __construct(private LedgerPostingService $ledger, private DailyClosingService $closing) {}
    public function reverse(CustomerSettlement|SupplierPayment $payment, string $reason, int $actor): void {
        DB::transaction(function () use ($payment,$reason,$actor) {
            $customer = $payment instanceof CustomerSettlement;
            $invoice = $customer ? Sale::query()->lockForUpdate()->findOrFail($payment->sale_id) : PurchaseInvoice::query()->lockForUpdate()->findOrFail($payment->purchase_invoice_id);
            $entry = JournalEntry::query()->where('source_type',$payment::class)->where('source_id',$payment->id)->where('source_event','paid')->lockForUpdate()->firstOrFail();
            if ($entry->status === 'reversed') return;
            $date = $this->closing->businessDate();
            if ($customer && ($invoice->status !== 'completed' || $this->closing->salesBlocked($invoice->location,$date))) {
                throw ValidationException::withMessages(['reason'=>'Sale unavailable or business day closed.']);
            }
            if (!$customer && $invoice->status === 'cancelled') {
                throw ValidationException::withMessages(['reason'=>'Cancelled invoice cannot be adjusted.']);
            }
            $amount = BigDecimal::of($payment->amount);
            $paid = BigDecimal::of($invoice->paid_total)->minus($amount);
            if ($paid->isNegative()) throw ValidationException::withMessages(['reason'=>'Payment balance requires reconciliation.']);
            $column = $customer ? 'due_total' : 'balance_due';
            $due = BigDecimal::of($invoice->$column)->plus($amount);
            $reversal = $this->ledger->reverse($entry,$reason,$actor);
            $reversal->update(['business_date'=>$date]);
            $invoice->update(['paid_total'=>(string)$paid,$column=>(string)$due,
                ($customer?'payment_status':'status')=>$customer?($paid->isZero()?'credit':'partial'):($paid->isZero()?'posted':'partially_paid')]);
        });
    }
}
