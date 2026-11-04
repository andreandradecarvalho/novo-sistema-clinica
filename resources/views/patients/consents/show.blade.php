@extends('layouts.app')
@section('title', 'Manifestação registrada')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Manifestação registrada</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card"><h2 class="card-header h5">{{ $consent->purpose }}</h2><div class="card-body">
    <dl class="row">
        <dt class="col-sm-4">Manifestação</dt><dd class="col-sm-8">{{ $consent->decision->label() }}</dd>
        <dt class="col-sm-4">Base informada</dt><dd class="col-sm-8">{{ $consent->legal_basis }}</dd>
        <dt class="col-sm-4">Versão do termo</dt><dd class="col-sm-8">{{ $consent->policy_version }}</dd>
        <dt class="col-sm-4">Data (UTC)</dt><dd class="col-sm-8">{{ $consent->occurred_at->format('d/m/Y H:i:s') }}</dd>
        <dt class="col-sm-4">Responsável pelo lançamento</dt><dd class="col-sm-8">Integrante #{{ $consent->recorded_by }}</dd>
    </dl>
    <h3 class="h6">Termo apresentado</h3><p class="text-break" style="white-space: pre-wrap">{{ $evidence['policy_text'] }}</p>
    <h3 class="h6">Evidência registrada</h3><p class="text-break" style="white-space: pre-wrap">{{ $evidence['evidence_note'] }}</p>
    <a class="btn btn-label-secondary" href="{{ route('patients.consents.index', $patient->public_id) }}">Voltar ao histórico</a>
</div></section>
@endsection
