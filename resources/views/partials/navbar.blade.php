<nav id="layout-navbar" class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" aria-label="Barra da clínica">
    <button type="button" class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none btn p-1" data-layout-toggle aria-controls="layout-menu" aria-expanded="false" aria-label="Abrir menu">
        <x-icon name="menu-2" class="icon-24px" />
    </button>
    <div class="navbar-nav-right d-flex align-items-center justify-content-between w-100">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-primary"><x-icon name="building-hospital" class="icon-22px" /></span></span>
            <div class="min-w-0"><span class="d-block fw-medium text-heading text-truncate clinic-name">{{ app(\App\Domain\Tenancy\Services\TenantContext::class)->require()->name }}</span><small class="text-body-secondary">Área da clínica</small></div>
        </div>
        <ul class="navbar-nav flex-row align-items-center ms-3">
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <button type="button" class="nav-link dropdown-toggle hide-arrow border-0 bg-transparent d-flex align-items-center gap-3 p-0" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu da conta">
                    <span class="d-none d-md-block text-end"><span class="d-block fw-medium text-heading">{{ auth()->user()->name }}</span><small class="text-body-secondary">Minha conta</small></span>
                    <span class="avatar avatar-online"><span class="avatar-initial rounded-circle bg-label-primary">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-4 py-2"><span class="d-block fw-medium text-heading">{{ auth()->user()->name }}</span><small class="text-body-secondary">{{ auth()->user()->email }}</small></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a href="{{ route('security') }}" class="dropdown-item"><x-icon name="shield-lock" class="me-2" />Segurança da conta</a></li>
                    <li class="px-3 py-2"><form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-danger d-flex align-items-center justify-content-center w-100" type="submit"><x-icon name="logout" class="me-2" />Sair</button></form></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
