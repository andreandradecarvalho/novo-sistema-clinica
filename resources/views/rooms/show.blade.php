@extends('layouts.app')
@section('title', 'Sala da unidade')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $room->name }}</h1><p class="text-body-secondary">{{ $unit->name }}</p></div>
<section class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">Cadastro</h2>@can('update', $room)<a class="btn btn-primary" href="{{ route('units.rooms.edit', [$unit->public_id, $room->id]) }}">Editar sala</a>@endcan</div><div class="card-body"><dl class="row"><dt class="col-sm-3">Chave</dt><dd class="col-sm-9">{{ $room->room_key }}</dd><dt class="col-sm-3">Ordem</dt><dd class="col-sm-9">{{ $room->ordem }}</dd><dt class="col-sm-3">Situação</dt><dd class="col-sm-9">{{ $room->is_active ? 'Ativa' : 'Inativa' }}</dd></dl><a class="btn btn-label-secondary" href="{{ route('units.rooms.index', $unit->public_id) }}">Voltar às salas</a></div></section>
@endsection
