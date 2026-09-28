@extends('layouts.pharmacy')

@section('title', 'POS — '.__('pharmacy.product'))

@section('content')
<div class="mx-auto max-w-7xl" x-data="pharmacyPos()">
    <div class="mb-5 flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
        <div>
            <p class="text-sm font-semibold text-teal-700">Point of Sale</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">New sale</h1>
            <p class="mt-1 text-sm text-slate-500">FEFO batch allocation, split settlement and AFN totals.</p>
        </div>
        <select x-model="location" @change="clearCart()" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 lg:w-80">
            @foreach ($locations as $location)
                <option value="{{ $location->id }}">{{ $location->branch?->name }} · {{ $location->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($locations->isEmpty())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900">Create an active inventory location before using POS.</div>
    @else
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_430px]">
            <section class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Scan barcode or search medicine</label>
                    <div class="mt-2 flex gap-2">
                        <input x-ref="search" x-model="query" @keydown.enter.prevent="search()" autocomplete="off" placeholder="Barcode, code, brand, generic or strength" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3">
                        <button type="button" @click="search()" class="rounded-xl bg-slate-900 px-5 py-3 font-bold text-white">Search</button>
                    </div>
                    <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                </div>

                <div x-show="results.length" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <template x-for="item in results" :key="item.id">
                        <button type="button" @click="add(item)" class="flex w-full items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 text-start last:border-0 hover:bg-slate-50">
                            <span><span class="block font-semibold" x-text="item.name + (item.strength ? ' · '+item.strength : '')"></span><span class="text-xs text-slate-500" x-text="(item.generic_name || '—')+' · '+item.code+' · Stock '+item.available"></span></span>
                            <strong class="shrink-0 text-teal-700" x-text="money(item.price)+' AFN'"></strong>
                        </button>
                    </template>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-4 py-3 font-bold">Cart</div>
                    <div x-show="cart.length===0" class="px-4 py-12 text-center text-sm text-slate-500">Scan or search a medicine to begin.</div>
                    <template x-for="(item,index) in cart" :key="item.id">
                        <div class="grid gap-3 border-b border-slate-100 px-4 py-4 md:grid-cols-[1fr_110px_130px_130px_40px] md:items-center">
                            <div><p class="font-semibold" x-text="item.name"></p><p class="text-xs text-slate-500" x-text="item.code+' · '+item.sale_unit"></p><p x-show="item.prescription_required" class="mt-1 text-xs font-semibold text-amber-700">Prescription item — pharmacist review required.</p></div>
                            <input type="number" min="0.0001" step="0.0001" x-model.number="item.quantity" class="rounded-lg border border-slate-300 px-2 py-2 text-end">
                            <input type="number" min="0" step="0.01" x-model.number="item.unit_price" class="rounded-lg border border-slate-300 px-2 py-2 text-end">
                            <input type="number" min="0" step="0.01" x-model.number="item.discount_amount" class="rounded-lg border border-slate-300 px-2 py-2 text-end" placeholder="Discount">
                            <button type="button" @click="cart.splice(index,1)" class="text-xl text-red-600">×</button>
                        </div>
                    </template>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="flex items-center justify-between"><h2 class="font-bold">Customer & totals</h2><a href="{{ route('pharmacy.customers.create') }}" class="text-xs font-bold text-teal-700">+ New customer</a></div>
                    <select x-model="customerId" class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                        <option value="">Walk-in customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                        @endforeach
                    </select>
                    <div class="mt-5 space-y-2 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><strong x-text="money(subtotal)+' AFN'"></strong></div>
                        <div class="flex justify-between"><span>Discount</span><strong x-text="money(discountTotal)+' AFN'"></strong></div>
                        <div class="flex justify-between border-t pt-3 text-xl"><span class="font-bold">Total</span><strong x-text="money(grandTotal)+' AFN'"></strong></div>
                    </div>
                </div>

                <div x-show="requiresPrescription" class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <h2 class="font-bold text-amber-950">Prescription details</h2>
                    <p class="mt-1 text-xs text-amber-800">Required because the cart contains a prescription-only medicine.</p>
                    <input x-model="prescriptionReference" placeholder="Prescription / Rx reference" class="mt-3 w-full rounded-xl border border-amber-300 bg-white px-3 py-2.5">
                    <input x-model="prescriberName" placeholder="Prescriber / doctor name" class="mt-2 w-full rounded-xl border border-amber-300 bg-white px-3 py-2.5">
                    <input x-model="prescriptionDate" type="date" class="mt-2 w-full rounded-xl border border-amber-300 bg-white px-3 py-2.5">
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="flex justify-between"><h2 class="font-bold">Payments</h2><button type="button" @click="payments.push({method:'cash',amount:0,reference:''})" class="text-sm font-semibold text-teal-700">+ Split</button></div>
                    <template x-for="(payment,index) in payments" :key="index">
                        <div class="mt-3 grid grid-cols-[110px_1fr_32px] gap-2">
                            <select x-model="payment.method" class="rounded-lg border border-slate-300 px-2 py-2 text-sm"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile">Mobile</option><option value="credit">Credit</option></select>
                            <input type="number" min="0" step="0.01" x-model.number="payment.amount" class="rounded-lg border border-slate-300 px-2 py-2 text-end">
                            <button type="button" @click="payments.splice(index,1)" class="text-red-600">×</button>
                        </div>
                    </template>
                    <button type="button" @click="fillCash()" class="mt-3 text-xs font-semibold text-teal-700">Set cash to total</button>
                </div>

                <button type="button" @click="checkout()" :disabled="busy || !cart.length" class="w-full rounded-2xl bg-teal-700 px-5 py-4 font-bold text-white disabled:opacity-50"><span x-show="!busy">Complete sale</span><span x-show="busy">Processing…</span></button>
            </aside>
        </div>
    @endif
</div>

<script>
function pharmacyPos() {
    return {
        location: @js((string) ($locations->first()?->id ?? '')), customerId:'', query:'', results:[], cart:[],
        payments:[{method:'cash',amount:0,reference:''}], prescriptionReference:'', prescriberName:'', prescriptionDate:new Date().toISOString().slice(0,10), busy:false, error:'',
        get subtotal(){return this.cart.reduce((t,i)=>t+(Number(i.quantity)||0)*(Number(i.unit_price)||0),0)},
        get discountTotal(){return this.cart.reduce((t,i)=>t+(Number(i.discount_amount)||0),0)},
        get grandTotal(){return Math.max(0,this.subtotal-this.discountTotal)},
        get requiresPrescription(){return this.cart.some(i=>i.prescription_required)},
        money(v){return Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})},
        clearCart(){this.cart=[];this.results=[];this.query='';this.prescriptionReference='';this.prescriberName='';this.fillCash()},
        async search(){this.error='';if(!this.query.trim()||!this.location)return;const url=@js(route('pharmacy.pos.search'))+'?'+new URLSearchParams({q:this.query,location:this.location});const r=await fetch(url,{headers:{Accept:'application/json'}});const j=await r.json();this.results=j.data||[];if(this.results.length===1&&(this.results[0].barcode===this.query||this.results[0].code===this.query))this.add(this.results[0])},
        add(item){const x=this.cart.find(v=>v.id===item.id);if(x)x.quantity=Number(x.quantity)+1;else this.cart.push({...item,quantity:1,unit_price:Number(item.price),discount_amount:0});this.query='';this.results=[];this.fillCash();this.$nextTick(()=>this.$refs.search?.focus())},
        fillCash(){if(this.payments.length===1&&this.payments[0].method==='cash')this.payments[0].amount=Number(this.grandTotal.toFixed(2))},
        async checkout(){this.error='';if(this.requiresPrescription&&!this.prescriptionReference.trim()){this.error='Prescription reference is required for prescription-only medicines.';return}this.busy=true;const payload={stock_location_id:this.location,customer_id:this.customerId||null,prescription_reference:this.prescriptionReference||null,prescriber_name:this.prescriberName||null,prescription_date:this.requiresPrescription?this.prescriptionDate:null,idempotency_key:crypto.randomUUID(),lines:this.cart.map(x=>({medicine_id:x.id,quantity:x.quantity,unit_price:x.unit_price,discount_amount:x.discount_amount||0})),payments:this.payments};try{const r=await fetch(@js(route('pharmacy.pos.store')),{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':@js(csrf_token())},body:JSON.stringify(payload)});const j=await r.json();if(!r.ok){this.error=Object.values(j.errors||{}).flat()[0]||j.message||'Sale could not be completed.';return}window.location=j.receipt_url}finally{this.busy=false}}
    }
}
</script>
@endsection
