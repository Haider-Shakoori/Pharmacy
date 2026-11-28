@if($invoices->count()>1)
<form method="POST" action="{{ route('pharmacy.'.($isCustomer?'customers':'suppliers').'.bulk-payment',$party) }}" class="mx-auto mt-5 max-w-4xl space-y-3 rounded-xl border bg-white p-5">
@csrf
<h2 class="font-bold">{{ __('accounts.bulk') }}</h2>
<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label>{{ __('accounts.amount') }} <input class="rounded border p-2" name="amount" type="number" min="0.0001" step="0.0001" required></label>
<select name="currency" class="rounded border p-2">@foreach($invoices->pluck('currency')->unique() as $currency)<option>{{ $currency }}</option>@endforeach</select>
<label>{{ __('accounts.method') }} <select name="method" class="rounded border p-2">@foreach($isCustomer?['cash','bank','mobile']:['cash','bank','hawala','other'] as $method)<option value="{{ $method }}">{{ __('accounts.'.$method) }}</option>@endforeach</select></label>
<label>{{ __('accounts.reference') }} <input name="reference" maxlength="160" class="rounded border p-2"></label>
<button class="rounded bg-teal-700 p-2 text-white">{{ __('accounts.'.($isCustomer?'receive_payment':'make_payment')) }}</button>
</form>
@endif
