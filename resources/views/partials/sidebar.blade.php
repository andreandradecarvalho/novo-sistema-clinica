<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme" aria-label="Menu principal">
    <div class="app-brand">
        <x-brand />
        <button type="button" class="layout-menu-toggle menu-link text-large ms-auto btn p-1" data-layout-toggle aria-controls="layout-menu" aria-expanded="true" aria-label="Recolher menu">
            <x-icon name="chevron-left" class="icon-20px" />
        </button>
    </div>
    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-4">
        <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" class="menu-link" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <x-icon name="layout-dashboard" class="menu-icon" /><span>Início</span>
            </a>
        </li>
        @can('viewAny', \App\Domain\Identity\Models\User::class)
        <li class="menu-header small"><span class="menu-header-text">Clínica</span></li>
        <li class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <a href="{{ route('users.index') }}" class="menu-link" @if(request()->routeIs('users.*')) aria-current="page" @endif>
                <x-icon name="users" class="menu-icon" /><span>Equipe</span>
            </a>
        </li>
        @endcan
        <li class="menu-header small"><span class="menu-header-text">Minha conta</span></li>
        <li class="menu-item {{ request()->routeIs('security') ? 'active' : '' }}">
            <a href="{{ route('security') }}" class="menu-link" @if(request()->routeIs('security')) aria-current="page" @endif>
                <x-icon name="shield-lock" class="menu-icon" /><span>Segurança</span>
            </a>
        </li>
    </ul>
</aside>
