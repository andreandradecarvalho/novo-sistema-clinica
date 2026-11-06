@extends('layouts.app')
@section('title', 'Tentativas dos exames')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Tentativas dos exames</h1><p class="text-body-secondary">{{ $patient->name }} · Atendimento {{ $visit->public_id }}</p></div>
@foreach(['outcome', 'execution', 'exam_order_item_id', 'idempotency_key', 'expected_status', 'reason'] as $field) @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach
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
<tr><td>{{ $execution->item->description_snapshot }}</td><td>{{ $execution->attempt_no }}</td><td>{{ ['pending' => 'Pendente de atribuição', 'in_progress' => 'Em execução', 'completed' => 'Execução concluída', 'refused' => 'Recusada após início', 'incomplete' => 'Execução incompleta', 'canceled' => 'Cancelada antes da execução'][$execution->status] }}</td><td>
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
@if(isset($evidence['exception']))
<p>Desfecho em {{ $evidence['exception']['occurred_at'] }} pelo profissional #{{ $evidence['exception']['professional_id'] }}.</p>@isset($evidence['exception']['reason'])<p class="text-break">Motivo: {{ $evidence['exception']['reason'] }}</p>@else<p>Motivo clínico restrito.</p>@endisset<p>Política financeira: {{ \App\Domain\Orders\Enums\ExceptionFinancialMode::from($evidence['exception']['financial_policy']['mode'])->label() }} · versão {{ $evidence['exception']['financial_policy']['revision'] }}.</p>
@elseif($execution->status === 'in_progress')
@can('resolveException', $execution)
<form method="post" action="{{ route('patients.visits.executions.exception', [$patient->public_id, $visit->public_id, $execution->id]) }}" class="mb-3">
@csrf<input type="hidden" name="expected_status" value="in_progress"><input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label class="form-label" for="outcome-{{ $execution->id }}">Desfecho excepcional</label><select class="form-select mb-3" name="outcome" id="outcome-{{ $execution->id }}" required><option value="">Selecione</option><option value="refused">Recusa após início</option><option value="incomplete">Execução incompleta</option></select>
<label class="form-label" for="exception-reason-{{ $execution->id }}">Motivo do desfecho</label><textarea class="form-control mb-3" id="exception-reason-{{ $execution->id }}" name="reason" maxlength="1000" rows="2" required></textarea>
<p class="form-text">O início será preservado. A política financeira da clínica precisa estar configurada; contas existentes exigem revisão se o valor apurado divergir.</p><button class="btn btn-sm btn-warning" type="submit">Registrar desfecho excepcional</button>
</form>
@endcan
@endif
@if(isset($evidence['retry']))
<p>Repetida pela tentativa #{{ $evidence['retry']['next_id'] }} em {{ $evidence['retry']['occurred_at'] }}.</p>
@isset($evidence['retry']['reason'])<p class="text-break">Motivo da repetição: {{ $evidence['retry']['reason'] }}</p>@endisset
@elseif(in_array($execution->status, ['refused', 'incomplete'], true) || ($execution->status === 'canceled' && isset($evidence['repeat_of'])))
@if($visit->status === 'in_progress')
@can('repeat', $execution)
<form method="post" action="{{ route('patients.visits.executions.repeat', [$patient->public_id, $visit->public_id, $execution->id]) }}" class="mb-3">
@csrf<input type="hidden" name="expected_status" value="{{ $execution->status }}"><input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label class="form-label" for="repeat-reason-{{ $execution->id }}">Motivo da repetição</label><textarea class="form-control mb-3" id="repeat-reason-{{ $execution->id }}" name="reason" maxlength="1000" rows="2" required></textarea>
<p class="form-text">Preserva a tentativa anterior e prepara outra no mesmo item. Cobrança e baixa ficarão bloqueadas até revisão financeira explícita.</p><button class="btn btn-sm btn-warning" type="submit">Preparar repetição</button>
</form>
@endcan
@endif
@endif
@isset($evidence['repeat_of'])<p>Repetição da tentativa #{{ $evidence['repeat_of']['previous_id'] }}. Revisão financeira pendente.</p>@endisset
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
