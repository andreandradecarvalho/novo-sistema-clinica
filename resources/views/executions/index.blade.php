@extends('layouts.app')
@section('title', 'Tentativas dos exames')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Tentativas dos exames</h1><p class="text-body-secondary">{{ $patient->name }} · Atendimento {{ $visit->public_id }}</p></div>
@foreach(['execution', 'exam_order_item_id', 'idempotency_key', 'expected_status', 'reason'] as $field) @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach
@if(in_array($visit->status, ['open', 'in_progress'], true) && ($items?->isNotEmpty() ?? false))
@can('prepare', [\App\Domain\Executions\Models\ExamExecution::class, $visit])
<section class="card mb-6"><h2 class="card-header h5">Preparar exame</h2><div class="card-body"><form method="post" action="{{ route('patients.visits.executions.store', [$patient->public_id, $visit->public_id]) }}">
@csrf<input type="hidden" name="expected_status" value="{{ $visit->status }}"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
<label class="form-label" for="exam_order_item_id">Item autorizado do pedido</label><select class="form-select mb-3" id="exam_order_item_id" name="exam_order_item_id" required><option value="">Selecione o exame</option>@foreach($items ?? [] as $item)<option value="{{ $item->id }}">#{{ $item->id }} · {{ $item->description_snapshot }}</option>@endforeach</select>
<p class="form-text">A preparação fica pendente de atribuição. Não registra execução nem resultado.</p><button type="submit" class="btn btn-primary">Preparar tentativa</button>
</form></div></section>
@endcan
@endif
<section class="card"><h2 class="card-header h5">{{ $executions->total() }} tentativas</h2><div class="table-responsive"><table class="table align-middle"><caption class="visually-hidden">Histórico de tentativas deste atendimento</caption><thead><tr><th scope="col">Exame</th><th scope="col">Tentativa</th><th scope="col">Situação</th><th scope="col">Registro / ações</th></tr></thead><tbody>
@forelse($executions as $execution)
@php($evidence = $histories[$execution->id])
<tr><td>{{ $execution->item->description_snapshot }}</td><td>{{ $execution->attempt_no }}</td><td>{{ ['pending' => 'Pendente de atribuição', 'in_progress' => 'Em execução', 'completed' => 'Execução concluída', 'canceled' => 'Cancelada antes da execução'][$execution->status] }}</td><td>
@if(isset($evidence['room_assignment']))<p>Sala atribuída: {{ $evidence['room_assignment']['room_key'] }} · {{ $evidence['room_assignment']['room_name'] }}.</p><p>Em {{ $evidence['room_assignment']['occurred_at'] }} por integrante #{{ $evidence['room_assignment']['actor_id'] }}.</p>
@elseif($execution->status === 'pending' && in_array($visit->status, ['open', 'in_progress'], true))
@can('assignRoom', $execution)<a class="btn btn-sm btn-label-primary mb-3" href="{{ route('patients.visits.executions.room', [$patient->public_id, $visit->public_id, $execution->id]) }}">Atribuir sala</a>@endcan
@endif
@if(isset($evidence['start']))<p>Iniciada em {{ $evidence['start']['occurred_at'] }} pelo profissional #{{ $evidence['start']['professional_id'] }} · concessão #{{ $evidence['start']['authorization_id'] }}.</p>
@elseif($execution->status === 'pending' && isset($evidence['room_assignment']))
@can('start', $execution)<form method="post" action="{{ route('patients.visits.executions.start', [$patient->public_id, $visit->public_id, $execution->id]) }}" class="mb-3">@csrf<input type="hidden" name="expected_status" value="pending"><input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><p class="form-text">Ao iniciar, você assume a execução com sua concessão vigente para este exame.</p><button type="submit" class="btn btn-sm btn-primary">Assumir e iniciar exame</button></form>@endcan
@endif
@if(isset($evidence['completion']))
<p>Concluída em {{ $evidence['completion']['occurred_at'] }} pelo profissional #{{ $evidence['completion']['professional_id'] }} · concessão #{{ $evidence['completion']['authorization_id'] }}.</p>
@elseif($execution->status === 'in_progress')
@can('complete', $execution)
<form method="post" action="{{ route('patients.visits.executions.complete', [$patient->public_id, $visit->public_id, $execution->id]) }}" class="mb-3">
@csrf<input type="hidden" name="expected_status" value="in_progress"><input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<p class="form-text">Confirme a conclusão da execução. Resultados e liberação de laudos são registrados separadamente.</p><button type="submit" class="btn btn-sm btn-primary">Concluir execução</button>
</form>
@endcan
@endif
<p>Preparada em {{ $evidence['occurred_at'] }} por integrante #{{ $evidence['actor_id'] }}.</p>
@if(isset($evidence['cancellation']))<p>Cancelada em {{ $evidence['cancellation']['occurred_at'] }} por integrante #{{ $evidence['cancellation']['actor_id'] }}.</p><p class="text-break">Motivo: {{ $evidence['cancellation']['reason'] }}</p>
@elseif($execution->status === 'pending' && in_array($visit->status, ['open', 'in_progress'], true))
@can('cancel', $execution)
<form method="post" action="{{ route('patients.visits.executions.cancel', [$patient->public_id, $visit->public_id, $execution->id]) }}">
@csrf<input type="hidden" name="expected_status" value="pending"><input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label class="form-label" for="reason-{{ $execution->id }}">Motivo operacional</label><textarea class="form-control mb-3" id="reason-{{ $execution->id }}" name="reason" rows="2" maxlength="1000" required></textarea><button type="submit" class="btn btn-sm btn-danger">Cancelar tentativa</button>
</form>
@endcan
@endif
</td></tr>
@empty<tr><td colspan="4" class="text-center py-8 text-body-secondary">Nenhuma tentativa preparada.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $executions->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('patients.visits.show', [$patient->public_id, $visit->public_id]) }}">Voltar ao atendimento</a></div></section>
@endsection
