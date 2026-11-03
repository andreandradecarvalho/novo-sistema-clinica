@extends('layouts.base')
@push('styles')
    @vite('resources/css/auth.css')
@endpush
@section('body')
<div class="authentication-wrapper authentication-cover">
    <div class="authentication-inner row m-0">
        <aside class="d-none d-lg-flex col-lg-7 p-0 auth-cover-panel" aria-label="Precordis">
            <div class="auth-cover-bg auth-cover-bg-color d-flex flex-column align-items-center justify-content-center p-12">
                <x-brand class="auth-cover-brand" />
                <img src="@yield('illustration', Vite::asset('docs/layout/assets/img/illustrations/auth-login-illustration-light.png'))" class="auth-illustration auth-cover-illustration" alt="" width="560" height="560">
                <div class="auth-cover-copy text-center mt-8">
                    <h2 class="h4 mb-2">Cuidado em cada etapa.</h2>
                    <p class="mb-0 text-body-secondary">Um só lugar para a rotina da sua clínica.</p>
                </div>
            </div>
        </aside>
        <div class="d-flex col-12 col-lg-5 align-items-center justify-content-center authentication-bg p-6 p-sm-12">
            <main id="main-content" class="auth-form-content w-px-400">
                <div class="app-brand justify-content-center justify-content-lg-start mb-8 d-lg-none"><x-brand /></div>
                <p class="text-body-secondary small mb-2">{{ app(\App\Domain\Tenancy\Services\TenantContext::class)->current()?->name ?? 'Precordis' }}</p>
                <h1 class="h4 mb-2">@yield('heading')</h1>
                <p class="mb-6 text-body-secondary">@yield('description')</p>
                @include('partials.feedback')
                @yield('content')
                @auth
                    <form method="post" action="{{ route('logout') }}" class="mt-6 text-center">@csrf<button type="submit" class="btn btn-text-secondary">Sair da conta</button></form>
                @endauth
            </main>
        </div>
    </div>
</div>
@endsection
