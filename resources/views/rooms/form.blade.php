@extends('layouts.app')
@section('title', $room->exists ? 'Editar sala' : 'Nova sala')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $room->exists ? 'Editar sala' : 'Nova sala' }}</h1><p class="text-body-secondary">{{ $unit->name }}</p></div>
<section class="card"><h2 class="card-header h5">Identificação da sala</h2><div class="card-body">
@error('room')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
<form method="post" action="{{ $room->exists ? route('units.rooms.update', [$unit->public_id, $room->id]) : route('units.rooms.store', $unit->public_id) }}">
@csrf @if($room->exists) @method('PUT') @endif
<x-input name="room_key" label="Chave da sala" :value="$room->room_key" maxlength="20" />
<p class="form-text">Chave única nesta clínica, inclusive entre unidades. Use letras sem acento, números, hífen ou sublinhado.</p>
<x-input name="name" label="Nome da sala" :value="$room->name" maxlength="100" />
<x-input name="ordem" label="Ordem de exibição" type="number" :value="$room->ordem ?? 0" min="0" max="65535" />
<x-active-select :active="$room->exists ? $room->is_active : true" />
<div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Salvar sala</button><a class="btn btn-label-secondary" href="{{ route('units.rooms.index', $unit->public_id) }}">Cancelar</a></div>
</form></div></section>
@endsection
