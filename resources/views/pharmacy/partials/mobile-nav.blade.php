<nav class="grid gap-1 px-4 py-3 text-sm">
    @if (auth()->user()->hasPermission('dashboard.view'))
        <a href="{{ route('pharmacy.dashboard') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700">{{ __('pharmacy.nav.dashboard') }}</a>
    @endif
    @if (auth()->user()->hasPermission('medicines.manage'))
        <a href="{{ route('pharmacy.medicines.index') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700">{{ __('pharmacy.nav.medicines') }}</a>
    @endif
    @if (auth()->user()->hasAnyPermission(['purchases.manage', 'purchases.approve', 'purchases.pay']))
        <a href="{{ route('pharmacy.purchase-orders.index') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700">Purchasing</a>
    @endif
    @if (auth()->user()->hasPermission('purchases.manage'))
        <a href="{{ route('pharmacy.suppliers.index') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700">Suppliers</a>
    @endif
    @if (auth()->user()->hasPermission('users.manage'))
        <a href="{{ route('pharmacy.users.index') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700">{{ __('pharmacy.nav.users') }}</a>
    @endif
    @if (auth()->user()->hasPermission('roles.manage'))
        <a href="{{ route('pharmacy.roles.index') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700">Roles & Permissions</a>
    @endif
    @if (auth()->user()->hasPermission('settings.manage'))
        <a href="{{ route('pharmacy.settings.edit') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700">{{ __('pharmacy.nav.settings') }}</a>
    @endif
</nav>
