@extends('layouts.app')
@section('title', 'Exame do catálogo')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $examType->name }}</h1><p class="text-body-secondary">Dados do catálogo desta clínica.</p></div>
<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">Configuração do exame</h2>@can('update', $examType)<a class="btn btn-primary" href="{{ route('exam-types.edit', $examType->public_id) }}">Editar exame</a>@endcan</div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-4">Código</dt><dd class="col-sm-8">{{ $examType->code }}</dd>
            <dt class="col-sm-4">Situação</dt><dd class="col-sm-8">{{ $examType->is_active ? 'Ativo' : 'Inativo' }}</dd>
            <dt class="col-sm-4">Exige jejum?</dt><dd class="col-sm-8">{{ $examType->requires_fasting ? 'Sim' : 'Não' }}</dd>
            <dt class="col-sm-4">Duração prevista</dt><dd class="col-sm-8">{{ $examType->default_duration_min === null ? 'Não informada' : $examType->default_duration_min.' min' }}</dd>
            <dt class="col-sm-4">Preço de referência</dt><dd class="col-sm-8">{{ $examType->price ?? 'Não informado' }}</dd>
        </dl>
        <div class="d-flex gap-3 flex-wrap"><a class="btn btn-label-primary" href="{{ route('exam-types.preparations.index', $examType->public_id) }}">Preparos do exame</a><a class="btn btn-label-primary" href="{{ route('exam-types.attachments.index', $examType->public_id) }}">Anexos de orientação</a><a class="btn btn-label-secondary" href="{{ route('exam-types.index') }}">Voltar ao catálogo</a></div>
    </div>
</section>
@endsection
