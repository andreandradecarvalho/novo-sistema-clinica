@extends('layouts.app')
@section('title', 'Resposta clínica registrada')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Resposta clínica registrada</h1><p class="text-body-secondary">{{ $patient->name }} · Atendimento {{ $visit->public_id }}</p></div>
<section class="card"><h2 class="card-header h5">{{ $history['answer']['code'] }} · versão {{ $history['answer']['version'] }}</h2><div class="card-body">
<dl class="row"><dt class="col-sm-4">Coletada em (UTC)</dt><dd class="col-sm-8">{{ $history['occurred_at'] }}</dd><dt class="col-sm-4">Responsável pela coleta</dt><dd class="col-sm-8">Integrante #{{ $history['actor_id'] }}</dd><dt class="col-sm-4">Origem</dt><dd class="col-sm-8">Atendimento registrado</dd></dl>
<h3 class="h6 text-break">{{ $history['answer']['label'] }}</h3><p class="text-break" style="white-space: pre-wrap">{{ $history['answer']['answer'] }}</p>
<a class="btn btn-label-secondary" href="{{ route('patients.visits.health-records.index', [$patient->public_id, $visit->public_id]) }}">Voltar aos registros</a></div></section>
@endsection
