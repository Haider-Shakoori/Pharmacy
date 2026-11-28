<section class="mx-auto mt-5 max-w-7xl rounded-2xl border bg-white p-5">
<h2 class="mb-4 text-xl font-bold">{{ __('accounts.history') }}</h2>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr>@foreach(['date','amount','method','reference','print'] as $label)<th class="p-2 text-start">{{ __('accounts.'.$label) }}</th>@endforeach</tr></thead><tbody>
@forelse($settlements as $payment)<tr class="border-t"><td class="p-2">{{ $payment->paid_at }}</td><td>{{ $payment->amount }} {{ $payment->currency }}</td><td>{{ __('accounts.'.$payment->method) }}</td><td>{{ $payment->reference }}</td><td><a class="font-semibold text-teal-700" href="{{ route($receiptRoute, [$party,$payment]) }}">{{ __('accounts.print') }}</a>@if($payment->paymentJournal?->status === 'reversed')
<span class="block font-semibold text-red-700">{{ __('accounts.reversed') }}</span>
<span class="block text-xs">{{ $payment->paymentJournal->reversal_reason }}</span>
@elseif($payment->paymentJournal?->status === 'posted' && auth()->user()->hasPermission('accounting.manage'))
<form method="POST" action="{{ route(str_replace('.receipt','.reverse',$receiptRoute),[$party,$payment]) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('accounts.reverse_confirm') }}" class="mt-2 space-y-1">
@csrf
<input name="reason" required maxlength="500" placeholder="{{ __('accounts.reverse_reason') }}" class="block w-full rounded border p-1">
<button class="rounded border border-red-600 px-2 py-1 text-red-700">{{ __('accounts.reverse') }}</button>
</form>
@endif
</td></tr>
@empty<tr><td colspan="5">{{ __('accounts.no_payments') }}</td></tr>@endforelse
</tbody></table></div>{{ $settlements->links() }}</section>
