@extends('layouts.app')
@section('title', 'Atribuir sala')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Atribuir sala</h1><p class="text-body-secondary">{{ $patient->name }} · Tentativa {{ $execution->attempt_no }}</p></div>
@foreach(['execution', 'room_id', 'idempotency_key', 'expected_status'] as $field) @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach
<section class="card"><h2 class="card-header h5">Salas ativas da unidade do atendimento</h2><div class="card-body"><p>A atribuição de sala mantém a tentativa pendente de profissional. Não reserva horário nem inicia o exame.</p></div>
<div class="table-responsive"><table class="table table-hover align-middle"><caption class="visually-hidden">Escolher sala para esta tentativa</caption><thead><tr><th scope="col">Chave</th><th scope="col">Sala</th><th scope="col">Ação</th></tr></thead><tbody>
@forelse($rooms as $room)<tr><td>{{ $room->room_key }}</td><td>{{ $room->name }}</td><td><form method="post" action="{{ route('patients.visits.executions.room.store', [$patient->public_id, $visit->public_id, $execution->id]) }}">
@csrf<input type="hidden" name="expected_status" value="pending"><input type="hidden" name="room_id" value="{{ $room->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button type="submit" class="btn btn-sm btn-primary">Atribuir esta sala</button>
</form></td></tr>@empty<tr><td colspan="3" class="text-center py-8 text-body-secondary">Nenhuma sala ativa disponível nesta unidade.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $rooms->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('patients.visits.executions.index', [$patient->public_id, $visit->public_id]) }}">Voltar às tentativas</a></div></section>
@endsection
