@extends('layouts.app')
@section('title', $unit->exists ? 'Editar estabelecimento' : 'Novo estabelecimento')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $unit->exists ? 'Editar estabelecimento' : 'Novo estabelecimento' }}</h1><p class="text-body-secondary">{{ $company->name }}</p></div>
<section class="card"><h2 class="card-header h5">Dados do estabelecimento</h2><div class="card-body"><form method="post" action="{{ $unit->exists ? route('companies.units.update', [$company->public_id, $unit->id]) : route('companies.units.store', $company->public_id) }}">
@csrf @if($unit->exists) @method('PUT') @endif
<x-input name="name" label="Nome do estabelecimento" :value="$unit->name" maxlength="180" />
<x-location-fields :record="$unit" />
<x-active-select :active="$unit->exists ? $unit->is_active : true" />
<div class="d-flex gap-3"><button class="btn btn-primary" type="submit">Salvar estabelecimento</button><a class="btn btn-label-secondary" href="{{ route('companies.show', $company->public_id) }}">Cancelar</a></div>
</form></div></section>
@endsection
