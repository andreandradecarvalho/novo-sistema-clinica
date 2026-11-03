<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Precordis')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <header class="app-header">
        <a href="{{ url('/') }}" class="h4 mb-0">Precordis</a>
        <span>{{ app(\App\Domain\Tenancy\Services\TenantContext::class)->current()?->name }}</span>
        @auth
        <nav aria-label="Navegação principal">
            <a href="{{ route('security') }}">Segurança</a>
            @can('viewAny', \App\Domain\Identity\Models\User::class)<a href="{{ route('users.index') }}">Equipe</a>@endcan
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary" type="submit">Sair</button></form>
        </nav>
        @endauth
    </header>
    <main id="main-content">
        @if(session('status'))<div class="alert alert-info" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
