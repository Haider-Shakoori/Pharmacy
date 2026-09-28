@extends('layouts.pharmacy')

@section('title', 'POS — '.__('pharmacy.product'))
@section('fullscreen', '1')
@section('body_class', 'bg-slate-100 text-slate-900 antialiased overflow-hidden')

@section('content')
<div class="flex h-screen min-h-0 flex-col" x-data="pharmacyPos()" x-init="$nextTick(() => $refs.search?.focus())">
    <header class="z-20 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 shadow-sm sm:px-5">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('pharmacy.dashboard') }}" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-900 font-black text-white">Rx</a>
            <div class="min-w-0">
                <p class="truncate text-sm font-black">{{ app(\App\Support\Tenancy\TenantContext::class)->tenant()->name }}</p>
                <p class="text-xs text-slate-500">Full-screen Point of Sale · FEFO batch pricing</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <select x-model="location" @change="clearCart()" class="hidden max-w-72 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold md:block">
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->branch?->name }} · {{ $location->name }}</option>
                @endforeach
            </select>
            <a href="{{ route('pharmacy.pos.invoices') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold hover:bg-slate-50">Invoices</a>
            <button type="button" @click="toggleFullscreen()" class="hidden rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold hover:bg-slate-50 sm:block">Full screen</button>
            <a href="{{ route('pharmacy.dashboard') }}" class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-bold text-white">Exit POS</a>
        </div>
    </header>

    @if ($locations->isEmpty())
        <main class="grid flex-1 place-items-center p-6">
            <div class="max-w-lg rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-amber-900">Create an active inventory location before using POS.</div>
        </main>
    @else
        <div class="flex min-h-0 flex-1 flex-col xl:grid xl:grid-cols-[minmax(0,1fr)_430px]">
            <main class="flex min-h-0 flex-1 flex-col border-e border-slate-200">
                <div class="shrink-0 border-b border-slate-200 bg-white p-3 sm:p-4">
                    <div class="mb-3 md:hidden">
                        <select x-model="location" @change="clearCart()" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->branch?->name }} · {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <input x-ref="search" x-model="query" @keydown.enter.prevent="search()" autocomplete="off" placeholder="Scan barcode or search medicine, generic, code, strength…" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3 text-base outline-none ring-teal-200 focus:ring-4">
                        <button type="button" @click="search()" class="rounded-xl bg-teal-700 px-5 py-3 font-black text-white">Search</button>
                    </div>
                    <p x-show="error" x-text="error" class="mt-2 text-sm font-semibold text-red-600"></p>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
                    <div x-show="results.length" class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">Search results</div>
                        <template x-for="item in results" :key="item.id">
                            <button type="button" @click="add(item)" class="grid w-full gap-2 border-b border-slate-100 px-4 py-3 text-start last:border-0 hover:bg-teal-50 sm:grid-cols-[1fr_auto] sm:items-center">
                                <span>
                                    <span class="block font-bold" x-text="item.name + (item.strength ? ' · '+item.strength : '')"></span>
                                    <span class="block text-xs text-slate-500" x-text="(item.generic_name || '—')+' · '+item.code+' · Stock '+item.available+' · '+item.batches.length+' batch'+(item.batches.length===1?'':'es')"></span>
                                    <span x-show="item.batches.length > 1" class="mt-1 block text-xs font-semibold text-indigo-700">FEFO will automatically split quantity across batches when needed.</span>
                                </span>
                                <span class="shrink-0 text-end"><strong class="block text-teal-700" x-text="priceRange(item)"></strong><span class="text-xs text-slate-500">AFN / unit</span></span>
                            </button>
                        </template>
                    </div>

                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <div><h2 class="font-black">Cart</h2><p class="text-xs text-slate-500" x-text="cart.length+' item type'+(cart.length===1?'':'s')"></p></div>
                            <button type="button" x-show="cart.length" @click="clearCart()" class="text-xs font-bold text-red-600">Clear cart</button>
                        </div>
                        <div x-show="cart.length===0" class="px-4 py-16 text-center text-sm text-slate-500">Scan or search a medicine to begin a sale.</div>
                        <template x-for="(item,index) in cart" :key="item.id">
                            <div class="border-b border-slate-100 p-4 last:border-0">
                                <div class="grid gap-3 lg:grid-cols-[minmax(180px,1fr)_110px_150px_125px_40px] lg:items-start">
                                    <div>
                                        <p class="font-bold" x-text="item.name + (item.strength ? ' · '+item.strength : '')"></p>
                                        <p class="text-xs text-slate-500" x-text="item.code+' · '+item.sale_unit+' · Available '+item.available"></p>
                                        <p x-show="item.prescription_required" class="mt-1 text-xs font-bold text-amber-700">Prescription required</p>
                                    </div>
                                    <label class="text-xs font-bold text-slate-500">Qty
                                        <input type="number" min="0.0001" step="0.0001" x-model.number="item.quantity" @input="fillCash()" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-end text-sm">
                                    </label>
                                    <div>
                                        <p class="text-xs font-bold text-slate-500">Price</p>
                                        <div x-show="!item.override_price" class="mt-1 rounded-lg bg-slate-50 px-2 py-2 text-end text-sm font-bold" x-text="money(standardAverage(item))+' avg'"></div>
                                        <input x-show="item.override_price" type="number" min="0" step="0.01" x-model.number="item.unit_price" @input="fillCash()" class="mt-1 w-full rounded-lg border border-amber-300 bg-amber-50 px-2 py-2 text-end text-sm font-bold">
                                        <label x-show="canPriceOverride" class="mt-1 flex cursor-pointer items-center justify-end gap-1 text-[11px] font-semibold text-amber-700"><input type="checkbox" x-model="item.override_price" @change="prepareOverride(item)"> Override</label>
                                    </div>
                                    <label class="text-xs font-bold text-slate-500">Discount
                                        <input type="number" min="0" step="0.01" x-model.number="item.discount_amount" @input="fillCash()" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-end text-sm">
                                    </label>
                                    <button type="button" @click="cart.splice(index,1);fillCash()" class="mt-5 h-9 rounded-lg text-xl text-red-600 hover:bg-red-50">×</button>
                                </div>
                                <div class="mt-3 flex flex-col gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs sm:flex-row sm:items-center sm:justify-between">
                                    <div><span class="font-bold text-slate-600">FEFO batches:</span> <span class="text-slate-600" x-text="batchPlan(item)"></span></div>
                                    <div class="shrink-0 font-black" x-text="money(lineTotal(item))+' AFN'"></div>
                                </div>
                                <p x-show="Number(item.quantity) > Number(item.available)" class="mt-2 text-xs font-bold text-red-600">Requested quantity is greater than sellable stock.</p>
                            </div>
                        </template>
                    </section>
                </div>
            </main>

            <aside class="max-h-[48vh] shrink-0 overflow-y-auto bg-slate-50 p-3 sm:p-4 xl:max-h-none xl:min-h-0">
                <div class="space-y-4">
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between"><h2 class="font-black">Customer</h2><a href="{{ route('pharmacy.customers.create') }}" class="text-xs font-bold text-teal-700">+ New</a></div>
                        <select x-model="customerId" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option value="">Walk-in customer</option>
                            @foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach
                        </select>
                    </section>

                    <section x-show="requiresPrescription" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                        <h2 class="font-black text-amber-950">Prescription details</h2>
                        <p class="mt-1 text-xs text-amber-800">Required because the cart contains a prescription-only medicine.</p>
                        <input x-model="prescriptionReference" placeholder="Prescription / Rx reference" class="mt-3 w-full rounded-xl border border-amber-300 bg-white px-3 py-2.5 text-sm">
                        <input x-model="prescriberName" placeholder="Prescriber / doctor name" class="mt-2 w-full rounded-xl border border-amber-300 bg-white px-3 py-2.5 text-sm">
                        <input x-model="prescriptionDate" type="date" class="mt-2 w-full rounded-xl border border-amber-300 bg-white px-3 py-2.5 text-sm">
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><strong x-text="money(subtotal)+' AFN'"></strong></div>
                            <div class="flex justify-between"><span class="text-slate-500">Discount</span><strong x-text="money(discountTotal)+' AFN'"></strong></div>
                            <div class="flex justify-between border-t border-slate-100 pt-3 text-xl"><span class="font-black">Total</span><strong class="text-teal-700" x-text="money(grandTotal)+' AFN'"></strong></div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex justify-between"><h2 class="font-black">Payments</h2><button type="button" @click="payments.push({method:'cash',amount:0,reference:''})" class="text-sm font-bold text-teal-700">+ Split</button></div>
                        <template x-for="(payment,index) in payments" :key="index">
                            <div class="mt-3 grid grid-cols-[100px_1fr_34px] gap-2">
                                <select x-model="payment.method" @change="fillCash()" class="rounded-lg border border-slate-300 px-2 py-2 text-sm"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile">Mobile</option><option value="credit">Credit</option></select>
                                <input type="number" min="0" step="0.01" x-model.number="payment.amount" class="rounded-lg border border-slate-300 px-2 py-2 text-end text-sm">
                                <button type="button" @click="payments.splice(index,1)" class="rounded-lg text-red-600 hover:bg-red-50">×</button>
                            </div>
                        </template>
                        <button type="button" @click="fillCash(true)" class="mt-3 text-xs font-bold text-teal-700">Set cash to total</button>
                    </section>

                    <button type="button" @click="checkout()" :disabled="busy || !cart.length || hasShortage" class="w-full rounded-2xl bg-teal-700 px-5 py-4 text-lg font-black text-white shadow-sm disabled:opacity-50"><span x-show="!busy">Complete sale · <span x-text="money(grandTotal)"></span> AFN</span><span x-show="busy">Processing…</span></button>
                </div>
            </aside>
        </div>
    @endif
</div>

<script>
function pharmacyPos() {
    return {
        location: @js((string) ($locations->first()?->id ?? '')),
        customerId: '', query: '', results: [], cart: [],
        payments: [{method:'cash',amount:0,reference:''}],
        prescriptionReference: '', prescriberName: '', prescriptionDate: new Date().toISOString().slice(0,10),
        canPriceOverride: @js(auth()->user()->hasPermission('pos.price_override')),
        busy: false, error: '',
        get subtotal(){ return this.cart.reduce((t,i)=>t+this.lineSubtotal(i),0) },
        get discountTotal(){ return this.cart.reduce((t,i)=>t+(Number(i.discount_amount)||0),0) },
        get grandTotal(){ return Math.max(0,this.subtotal-this.discountTotal) },
        get requiresPrescription(){ return this.cart.some(i=>i.prescription_required) },
        get hasShortage(){ return this.cart.some(i=>Number(i.quantity||0)>Number(i.available||0)) },
        money(v){ return Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) },
        priceRange(item){ const min=Number(item.price_min??item.price??0), max=Number(item.price_max??item.price??0); return min===max?this.money(min):this.money(min)+'–'+this.money(max) },
        standardLineTotal(item){ let remaining=Number(item.quantity)||0,total=0; for(const batch of (item.batches||[])){if(remaining<=0)break;const take=Math.min(remaining,Number(batch.available)||0);total+=take*(Number(batch.sale_price)||0);remaining-=take} return total },
        standardAverage(item){ const q=Number(item.quantity)||0; return q>0?this.standardLineTotal(item)/q:0 },
        lineSubtotal(item){ return item.override_price?(Number(item.quantity)||0)*(Number(item.unit_price)||0):this.standardLineTotal(item) },
        lineTotal(item){ return Math.max(0,this.lineSubtotal(item)-(Number(item.discount_amount)||0)) },
        batchPlan(item){ let remaining=Number(item.quantity)||0, parts=[]; for(const batch of (item.batches||[])){if(remaining<=0)break;const take=Math.min(remaining,Number(batch.available)||0);if(take>0){parts.push((batch.batch_number||'Unbatched')+': '+take+' × '+this.money(batch.sale_price)+(batch.expires_at?' · exp '+batch.expires_at:''));remaining-=take}} if(remaining>0)parts.push('Short '+remaining); return parts.join(' | ')||'No eligible batch' },
        prepareOverride(item){ if(item.override_price)item.unit_price=Number(this.standardAverage(item).toFixed(2)); this.fillCash() },
        clearCart(){ this.cart=[]; this.results=[]; this.query=''; this.prescriptionReference=''; this.prescriberName=''; this.payments=[{method:'cash',amount:0,reference:''}]; this.error='' },
        async search(){ this.error=''; if(!this.query.trim()||!this.location)return; const url=@js(route('pharmacy.pos.search'))+'?'+new URLSearchParams({q:this.query,location:this.location}); const r=await fetch(url,{headers:{Accept:'application/json'}}); const j=await r.json(); if(!r.ok){this.error=j.message||'Search failed.';return} this.results=j.data||[]; if(this.results.length===1&&(this.results[0].barcode===this.query||this.results[0].code===this.query))this.add(this.results[0]) },
        add(item){ const existing=this.cart.find(v=>v.id===item.id); if(existing)existing.quantity=Number(existing.quantity)+1; else this.cart.push({...item,quantity:1,unit_price:Number(item.price||0),override_price:false,discount_amount:0}); this.query=''; this.results=[]; this.fillCash(); this.$nextTick(()=>this.$refs.search?.focus()) },
        fillCash(force=false){ if(this.payments.length===1&&this.payments[0].method==='cash'&&(force||true))this.payments[0].amount=Number(this.grandTotal.toFixed(2)) },
        async toggleFullscreen(){ try{ if(!document.fullscreenElement)await document.documentElement.requestFullscreen(); else await document.exitFullscreen() }catch(e){} },
        async checkout(){
            this.error='';
            if(this.hasShortage){this.error='One or more items exceed available sellable stock.';return}
            if(this.requiresPrescription&&!this.prescriptionReference.trim()){this.error='Prescription reference is required for prescription-only medicines.';return}
            if(this.cart.some(i=>Number(i.quantity||0)<=0)){this.error='Every cart quantity must be greater than zero.';return}
            this.busy=true;
            const payload={
                stock_location_id:this.location,
                customer_id:this.customerId||null,
                prescription_reference:this.prescriptionReference||null,
                prescriber_name:this.prescriberName||null,
                prescription_date:this.requiresPrescription?this.prescriptionDate:null,
                idempotency_key:crypto.randomUUID(),
                lines:this.cart.map(x=>({medicine_id:x.id,quantity:x.quantity,unit_price:x.override_price?x.unit_price:null,override_price:!!x.override_price,discount_amount:x.discount_amount||0})),
                payments:this.payments
            };
            try{
                const r=await fetch(@js(route('pharmacy.pos.store')),{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':@js(csrf_token())},body:JSON.stringify(payload)});
                const j=await r.json();
                if(!r.ok){this.error=Object.values(j.errors||{}).flat()[0]||j.message||'Sale could not be completed.';return}
                window.location=j.receipt_url
            }finally{this.busy=false}
        }
    }
}
</script>
@endsection
