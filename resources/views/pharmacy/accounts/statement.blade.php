<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ps']) ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><title>{{ __('accounts.statement') }} — {{ $party->name }}</title>
<style>body{font:15px sans-serif;margin:24px;color:#172033}table{width:100%;border-collapse:collapse}td,th{padding:8px;border:1px solid #ccc;text-align:start}button,input{padding:8px} @media print{.controls{display:none}body{margin:0}} @page{size:A4;margin:15mm}</style></head>
<body>
<h1>{{ __('accounts.statement') }}</h1><h2>{{ $party->name }}</h2><p>{{ $party->phone }}</p>
<form class="controls" method="get"><label>{{ __('accounts.from') }} <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
<label>{{ __('accounts.to') }} <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
<button>{{ __('accounts.filter') }}</button><button type="button" onclick="window.print()">{{ __('accounts.print') }}</button></form>
<p>{{ __('accounts.opening') }}: {{ $opening }} AFN</p>
<table><thead><tr>@foreach(['date','reference','description','debit','credit','balance'] as $label)<th>{{ __('accounts.'.$label) }}</th>@endforeach</tr></thead><tbody>
@foreach($rows as $row)<tr><td>{{ $row['line']->entry->business_date->toDateString() }}</td><td>{{ $row['line']->entry->source_number }}</td><td>{{ $row['line']->entry->description }}</td><td>{{ $row['line']->debit }}</td><td>{{ $row['line']->credit }}</td><td>{{ $row['balance'] }}</td></tr>@endforeach
</tbody></table><h3>{{ __('accounts.closing') }}: {{ $balance }} AFN</h3></body></html>
