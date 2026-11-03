@extends('layouts.app')
@section('title', $company->exists ? 'Editar empresa' : 'Nova empresa')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $company->exists ? 'Editar empresa' : 'Nova empresa' }}</h1><p class="text-body-secondary">Dados cadastrais da empresa cliente.</p></div>
<section class="card"><h2 class="card-header h5">Identificação</h2><div class="card-body"><form method="post" action="{{ $company->exists ? route('companies.update', $company->public_id) : route('companies.store') }}">
@csrf @if($company->exists) @method('PUT') @endif
<x-input name="name" label="Nome da empresa" :value="$company->name" maxlength="180" />
<x-input name="cnpj" label="CNPJ (opcional)" :value="$company->cnpj" :required="false" maxlength="18" autocapitalize="characters" />
<p class="small text-body-secondary">Aceita CNPJ numérico ou alfanumérico, com ou sem pontuação.</p>
<x-active-select :active="$company->exists ? $company->is_active : true" />
<div class="d-flex gap-3"><button class="btn btn-primary" type="submit">Salvar empresa</button><a class="btn btn-label-secondary" href="{{ route('companies.index') }}">Cancelar</a></div>
</form></div></section>
@endsection
