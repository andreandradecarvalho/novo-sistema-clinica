@extends('layouts.app')
@section('title', $unit->exists ? 'Editar unidade clínica' : 'Nova unidade clínica')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $unit->exists ? 'Editar unidade clínica' : 'Nova unidade clínica' }}</h1><p class="text-body-secondary">Local de atendimento da clínica.</p></div>
<section class="card"><h2 class="card-header h5">Identificação e localização</h2><div class="card-body"><form method="post" action="{{ $unit->exists ? route('units.update', $unit->public_id) : route('units.store') }}">
@csrf @if($unit->exists) @method('PUT') @endif
<x-input name="name" label="Nome da unidade" :value="$unit->name" maxlength="150" />
<x-location-fields :record="$unit" /><x-active-select :active="$unit->exists ? $unit->is_active : true" />
<div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Salvar unidade</button><a class="btn btn-label-secondary" href="{{ route('units.index') }}">Cancelar</a></div>
</form></div></section>
@endsection
