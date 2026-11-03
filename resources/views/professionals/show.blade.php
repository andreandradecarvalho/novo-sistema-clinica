@extends('layouts.app')
@section('title', 'Cadastro profissional')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $professional->user?->name ?? 'Integrante indisponível' }}</h1><p class="text-body-secondary">Cadastro profissional nesta clínica.</p></div>
<section class="card"><div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3"><h2 class="h5 mb-0">Dados cadastrais</h2>@can('update', $professional)<a class="btn btn-primary" href="{{ route('professionals.edit', $professional->id) }}">Editar profissional</a>@endcan</div><div class="card-body"><dl class="row"><dt class="col-sm-4">Registro</dt><dd class="col-sm-8">{{ $professional->council ?? 'Não informado' }} {{ $professional->council_number }} {{ $professional->council_uf }}</dd><dt class="col-sm-4">Especialidade</dt><dd class="col-sm-8">{{ $professional->specialty ?? 'Não informada' }}</dd><dt class="col-sm-4">Situação do integrante</dt><dd class="col-sm-8">{{ $professional->user?->is_active ? 'Ativo' : 'Inativo ou indisponível' }}</dd></dl><a class="btn btn-label-secondary" href="{{ route('professionals.index') }}">Voltar aos profissionais</a></div></section>
@endsection
