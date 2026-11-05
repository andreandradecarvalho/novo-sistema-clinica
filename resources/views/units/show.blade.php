@extends('layouts.app')
@section('title', 'Unidade clínica')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $unit->name }}</h1><p class="text-body-secondary">Unidade de atendimento da clínica.</p></div>
<section class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">Cadastro</h2>@can('update', $unit)<a class="btn btn-primary" href="{{ route('units.edit', $unit->public_id) }}">Editar unidade</a>@endcan</div><div class="card-body"><dl class="row"><dt class="col-sm-3">Cidade / UF</dt><dd class="col-sm-9">{{ $unit->city ?? 'Não informada' }} {{ $unit->state ? ' / '.$unit->state : '' }}</dd><dt class="col-sm-3">Situação</dt><dd class="col-sm-9">{{ $unit->is_active ? 'Ativa' : 'Inativa' }}</dd></dl>@can('viewAny', [\App\Domain\Scheduling\Models\Room::class, $unit])<a class="btn btn-label-primary me-3" href="{{ route('units.rooms.index', $unit->public_id) }}">Salas da unidade</a>@endcan
<a class="btn btn-label-secondary" href="{{ route('units.index') }}">Voltar às unidades</a></div></section>
@endsection
