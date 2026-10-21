@php
$navIcon = function (string $name): string {
    return match ($name) {
        'dashboard' => '<svg viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/></svg>',
        'alerts' => '<svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>',
        'pos' => '<svg viewBox="0 0 24 24"><path d="M4 5h16v11H4zM7 19h10M8 9h8M8 12h5"/></svg>',
        'invoice' => '<svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6M9 12h6M9 16h4"/></svg>',
        'customers' => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'medicine' => '<svg viewBox="0 0 24 24"><path d="m10 14 4-4M8.5 17.5l-2-2a5 5 0 0 1 7-7l2 2a5 5 0 0 1-7 7ZM14 6l4 4"/></svg>',
        'purchase' => '<svg viewBox="0 0 24 24"><path d="M3 5h2l2.4 10.2a2 2 0 0 0 2 1.5h7.7a2 2 0 0 0 2-1.6L21 8H7M10 21h.01M18 21h.01"/></svg>',
        'supplier' => '<svg viewBox="0 0 24 24"><path d="M3 21V8l9-5 9 5v13M8 21v-7h8v7M3 10h18"/></svg>',
        'inventory' => '<svg viewBox="0 0 24 24"><path d="m21 8-9 5-9-5 9-5 9 5ZM3 8v8l9 5 9-5V8M12 13v8"/></svg>',
        'closing' => '<svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'safe' => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M12 9V7M12 17v-2M9 12H7M17 12h-2"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
        'accounting' => '<svg viewBox="0 0 24 24"><path d="M3 6h18M5 10h14v10H5zM8 14h3M8 17h6"/></svg>',
        'users' => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6"/></svg>',
        'roles' => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM9 12l2 2 4-4"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg>',
        default => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>',
    };
};
$link = function(string $route, string $label, string $icon, bool $active, string $extra='') use ($navIcon) {
    $current = $active ? 'page' : 'false';
    return '<a href="'.route($route).'" aria-current="'.$current.'" class="dt-nav-item '.$extra.'"><span class="dt-nav-icon">'.$navIcon($icon).'</span><span class="dt-nav-text">'.e($label).'</span></a>';
};
@endphp
<div class="flex h-full min-h-screen flex-col">
    <div class="dt-brand">
        <div class="dt-brand-mark"><span>+</span></div>
        <div class="dt-sidebar-copy min-w-0">
            <p class="truncate text-[15px] font-black tracking-tight text-white">{{ __('pharmacy.product') }}</p>
            <p class="mt-0.5 truncate text-[10px] font-bold uppercase tracking-[.18em] text-blue-300">Pharmacy OS</p>
        </div>
    </div>

    <nav aria-label="Pharmacy modules" class="flex-1 px-3 pb-5">
        <p class="dt-nav-label">Workspace</p>
        @if(auth()->user()->hasPermission('dashboard.view')){!! $link('pharmacy.dashboard',__('pharmacy.nav.dashboard'),'dashboard',request()->routeIs('pharmacy.dashboard')) !!}{!! $link('pharmacy.alerts.index',__('pharmacy.nav.alerts'),'alerts',request()->routeIs('pharmacy.alerts.*')) !!}@endif
        @if(auth()->user()->hasPermission('pos.sell')){!! $link('pharmacy.pos.index',__('pharmacy.nav.pos'),'pos',request()->routeIs('pharmacy.pos.index'),'dt-nav-pos') !!}{!! $link('pharmacy.pos.invoices','POS Invoices','invoice',request()->routeIs('pharmacy.pos.invoices','pharmacy.pos.receipt')) !!}@endif
        @if(auth()->user()->hasPermission('customers.manage')){!! $link('pharmacy.customers.index',__('pharmacy.nav.customers'),'customers',request()->routeIs('pharmacy.customers.*')) !!}@endif

        <p class="dt-nav-label">Inventory & supply</p>
        @if(auth()->user()->hasPermission('medicines.manage')){!! $link('pharmacy.medicines.index',__('pharmacy.nav.medicines'),'medicine',request()->routeIs('pharmacy.medicines.*','pharmacy.medicine-references.*')) !!}@endif
        @if(auth()->user()->hasAnyPermission(['purchases.manage','purchases.approve','purchases.pay'])){!! $link('pharmacy.purchase-orders.index','Purchasing','purchase',request()->routeIs('pharmacy.purchase-*','pharmacy.supplier-payments.*')) !!}@endif
        @if(auth()->user()->hasPermission('purchases.manage')){!! $link('pharmacy.suppliers.index','Suppliers','supplier',request()->routeIs('pharmacy.suppliers.*')) !!}@endif
        @if(auth()->user()->hasPermission('inventory.manage')){!! $link('pharmacy.inventory.index',__('pharmacy.nav.inventory'),'inventory',request()->routeIs('pharmacy.inventory.*')) !!}@endif

        <p class="dt-nav-label">Finance & control</p>
        @if(auth()->user()->hasPermission('safe.view')){!! $link('pharmacy.safe.index',__('safe.nav'),'safe',request()->routeIs('pharmacy.safe.*')) !!}@endif
        @if(auth()->user()->hasPermission('accounting.manage')){!! $link('pharmacy.accounting.index','Accounting','accounting',request()->routeIs('pharmacy.accounting.*')) !!}@endif
        @if(auth()->user()->hasPermission('daily_closing.perform')){!! $link('pharmacy.daily-closing.index','Daily Closing','closing',request()->routeIs('pharmacy.daily-closing.*')) !!}@endif
        @if(auth()->user()->hasPermission('reports.view')){!! $link('pharmacy.reports.index',__('pharmacy.nav.reports'),'reports',request()->routeIs('pharmacy.reports.*')) !!}@endif

        <p class="dt-nav-label">Administration</p>
        @if(auth()->user()->hasPermission('users.manage')){!! $link('pharmacy.users.index',__('pharmacy.nav.users'),'users',request()->routeIs('pharmacy.users.*')) !!}@endif
        @if(auth()->user()->hasPermission('roles.manage')){!! $link('pharmacy.roles.index','Roles & Permissions','roles',request()->routeIs('pharmacy.roles.*')) !!}@endif
        @if(auth()->user()->hasPermission('settings.manage')){!! $link('pharmacy.settings.edit',__('pharmacy.nav.settings'),'settings',request()->routeIs('pharmacy.settings.*')) !!}@endif
    </nav>

    <div class="dt-sidebar-footer">
        <a href="{{ config('pharmacy.mobile.android_download_url') }}" download class="dt-app-card">
            <span class="dt-app-icon">↧</span>
            <span class="dt-sidebar-copy min-w-0"><strong>Mobile app</strong><small>Android · APK</small></span>
            <span class="dt-sidebar-copy ms-auto text-blue-300">›</span>
        </a>
    </div>
</div>