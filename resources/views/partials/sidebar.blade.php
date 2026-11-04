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
        @can('viewAny', \App\Domain\Patients\Models\Patient::class)
        <li class="menu-item {{ request()->routeIs('patients.*') ? 'active' : '' }}">
            <a href="{{ route('patients.index') }}" class="menu-link" @if(request()->routeIs('patients.*')) aria-current="page" @endif><x-icon name="users" class="menu-icon" /><span>Pacientes</span></a>
        </li>
        @endcan
        @can('viewAny', \App\Domain\Companies\Models\Company::class)
        <li class="menu-item {{ request()->routeIs('companies.*') ? 'active' : '' }}">
            <a href="{{ route('companies.index') }}" class="menu-link" @if(request()->routeIs('companies.*')) aria-current="page" @endif><x-icon name="users" class="menu-icon" /><span>Empresas</span></a>
        </li>
        @endcan
        @can('viewAny', \App\Domain\Tenancy\Models\Unit::class)
        <li class="menu-item {{ request()->routeIs('units.*') ? 'active' : '' }}"><a href="{{ route('units.index') }}" class="menu-link" @if(request()->routeIs('units.*')) aria-current="page" @endif><x-icon name="users" class="menu-icon" /><span>Unidades clínicas</span></a></li>
        @endcan
        @can('viewAny', \App\Domain\Professionals\Models\Professional::class)
        <li class="menu-item {{ request()->routeIs('professionals.*') ? 'active' : '' }}"><a href="{{ route('professionals.index') }}" class="menu-link" @if(request()->routeIs('professionals.*')) aria-current="page" @endif><x-icon name="users" class="menu-icon" /><span>Profissionais</span></a></li>
        @endcan
        @can('viewAny', \App\Domain\Catalog\Models\ExamType::class)
        <li class="menu-item {{ request()->routeIs('exam-types.*') ? 'active' : '' }}"><a href="{{ route('exam-types.index') }}" class="menu-link" @if(request()->routeIs('exam-types.*')) aria-current="page" @endif><x-icon name="layout-dashboard" class="menu-icon" /><span>Catálogo de exames</span></a></li>
        @endcan
        <li class="menu-header small"><span class="menu-header-text">Minha conta</span></li>
        <li class="menu-item {{ request()->routeIs('security') ? 'active' : '' }}">
            <a href="{{ route('security') }}" class="menu-link" @if(request()->routeIs('security')) aria-current="page" @endif>
                <x-icon name="shield-lock" class="menu-icon" /><span>Segurança</span>
            </a>
        </li>
    </ul>
</aside>
