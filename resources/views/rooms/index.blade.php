@extends('layouts.app')
@section('title', 'Salas da unidade')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Salas da unidade</h1><p class="text-body-secondary">{{ $unit->name }}</p></div>
<section class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">{{ $rooms->total() }} salas</h2>@can('create', [\App\Domain\Scheduling\Models\Room::class, $unit])<a class="btn btn-primary" href="{{ route('units.rooms.create', $unit->public_id) }}">Nova sala</a>@endcan</div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Salas cadastradas na unidade</caption><thead><tr><th scope="col">Chave</th><th scope="col">Nome</th><th scope="col">Ordem</th><th scope="col">Situação</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($rooms as $room)<tr><td>{{ $room->room_key }}</td><td>{{ $room->name }}</td><td>{{ $room->ordem }}</td><td><span @class(['badge', 'bg-label-success' => $room->is_active, 'bg-label-secondary' => !$room->is_active])>{{ $room->is_active ? 'Ativa' : 'Inativa' }}</span></td><td><a class="btn btn-sm btn-label-primary" href="{{ route('units.rooms.show', [$unit->public_id, $room->id]) }}">Consultar</a></td></tr>@empty<tr><td colspan="5" class="text-center py-8 text-body-secondary">Nenhuma sala cadastrada.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $rooms->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('units.show', $unit->public_id) }}">Voltar à unidade</a></div></section>
@endsection
