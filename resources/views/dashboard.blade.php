@extends('layouts.app')
@section('title', 'Início')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Visão geral</h1><p class="text-body-secondary mb-0">Bem-vindo ao ambiente da sua clínica.</p></div>
<div class="row g-6">
    <div class="col-12">
        <section class="card"><div class="row align-items-center g-0">
            <div class="col-md-8"><div class="card-body p-6 p-lg-8">
                <span class="badge bg-label-primary mb-4">{{ app(\App\Domain\Tenancy\Services\TenantContext::class)->require()->name }}</span>
                <h2 class="h4 text-primary mb-3">Olá, {{ auth()->user()->name }}</h2>
                <p class="mb-6">Escolha uma área no menu para continuar o trabalho da sua clínica.</p>
                <a href="{{ route('security') }}" class="btn btn-primary"><x-icon name="shield-lock" class="me-2" />Minha conta</a>
            </div></div>
            <div class="col-md-4 d-none d-md-flex align-self-end justify-content-center p-6 pb-0"><img src="{{ Vite::asset('docs/layout/assets/img/illustrations/boy-with-laptop-light.png') }}" class="welcome-illustration" alt="" width="240" height="220"></div>
        </div></section>
    </div>
    @can('viewAny', \App\Domain\Identity\Models\User::class)
    <div class="col-md-6">
        <section class="card h-100"><div class="card-body">
            <span class="avatar mb-4"><span class="avatar-initial rounded bg-label-primary"><x-icon name="users" class="icon-26px" /></span></span>
            <h2 class="h5">Equipe da clínica</h2><p class="text-body-secondary">Consulte os integrantes e a situação de acesso de cada conta.</p>
            <a href="{{ route('users.index') }}" class="d-inline-flex align-items-center gap-1">Ver equipe<x-icon name="chevron-right" /></a>
        </div></section>
    </div>
    @endcan
    <div class="col-md-6">
        <section class="card h-100"><div class="card-body">
            <span class="avatar mb-4"><span class="avatar-initial rounded bg-label-success"><x-icon name="shield-lock" class="icon-26px" /></span></span>
            <h2 class="h5">Segurança da conta</h2><p class="text-body-secondary">Gerencie sua senha, autenticação em dois fatores e sessões ativas.</p>
            <a href="{{ route('security') }}" class="d-inline-flex align-items-center gap-1">Gerenciar segurança<x-icon name="chevron-right" /></a>
        </div></section>
    </div>
</div>
@endsection
