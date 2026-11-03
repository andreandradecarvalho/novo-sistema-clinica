@extends('layouts.app')
@section('title', 'Integrante da equipe')
@section('content')
<div class="page-heading"><a href="{{ route('users.index') }}" class="d-inline-flex align-items-center gap-1 mb-3"><x-icon name="arrow-left" />Voltar à equipe</a><h1 class="h4 mb-0">Integrante da equipe</h1></div>
<div class="row g-6">
    <div class="col-lg-4"><section class="card h-100"><div class="card-body text-center py-8">
        <span class="avatar avatar-xl mb-4"><span class="avatar-initial rounded bg-label-primary">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($subject->name, 0, 1)) }}</span></span>
        <h2 class="h5 mb-3">{{ $subject->name }}</h2><span @class(['badge', 'bg-label-success' => $subject->is_active, 'bg-label-secondary' => !$subject->is_active])>{{ $subject->is_active ? 'Ativo' : 'Inativo' }}</span>
    </div></section></div>
    <div class="col-lg-8"><section class="card h-100"><h2 class="card-header h5">Dados da conta</h2><div class="card-body">
        <dl class="row mb-0"><dt class="col-sm-3 mb-3">Nome</dt><dd class="col-sm-9 mb-3">{{ $subject->name }}</dd><dt class="col-sm-3 mb-3">E-mail</dt><dd class="col-sm-9 mb-3 text-break">{{ $subject->email }}</dd><dt class="col-sm-3 mb-3">Clínica</dt><dd class="col-sm-9 mb-3">{{ app(\App\Domain\Tenancy\Services\TenantContext::class)->require()->name }}</dd></dl>
    </div></section></div>
</div>
@endsection
