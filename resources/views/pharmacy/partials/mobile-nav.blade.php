<nav class="grid gap-1 px-4 py-3 text-sm">
    @if (auth()->user()->hasPermission('dashboard.view'))
        <a href="{{ route('pharmacy.dashboard') }}" class="rounded-lg px-3 py-2 font-semibold {{ request()->routeIs('pharmacy.dashboard') ? 'bg-teal-50 text-teal-800' : 'text-slate-700' }}">{{ __('pharmacy.nav.dashboard') }}</a>
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
