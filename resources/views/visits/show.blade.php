@extends('layouts.app')
@section('title', ['open' => 'Atendimento aberto', 'in_progress' => 'Atendimento em execução', 'canceled' => 'Atendimento cancelado'][$visit->status])
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ ['open' => 'Atendimento aberto', 'in_progress' => 'Atendimento em execução', 'canceled' => 'Atendimento cancelado'][$visit->status] }}</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card"><h2 class="card-header h5">{{ $visit->public_id }}</h2><div class="card-body">
<dl class="row"><dt class="col-sm-4">Situação</dt><dd class="col-sm-8">{{ ['open' => 'Aberto', 'in_progress' => 'Em execução', 'canceled' => 'Cancelado'][$visit->status] }}</dd><dt class="col-sm-4">Check-in (UTC)</dt><dd class="col-sm-8">{{ $history['occurred_at'] }}</dd><dt class="col-sm-4">Integrante responsável pelo registro</dt><dd class="col-sm-8">#{{ $history['actor_id'] }}</dd><dt class="col-sm-4">Unidade no registro</dt><dd class="col-sm-8">{{ $visit->appointment->contextSnapshot()['unit_name'] }}</dd></dl>
<p>Atendimento vinculado à reserva e ao pedido. Consulte as tentativas para assumir e iniciar um exame. Respostas clínicas possuem coleta e consulta próprias.</p>
@can('viewAny', [\App\Domain\Clinical\Models\HealthRecord::class, $visit])<p><a class="btn btn-label-primary" href="{{ route('patients.visits.health-records.index', [$patient->public_id, $visit->public_id]) }}">Respostas clínicas</a></p>@endcan
@foreach(['visit', 'idempotency_key', 'expected_status'] as $field) @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach
@if(isset($history['cancellation']))
<p>Cancelado em {{ $history['cancellation']['occurred_at'] }} por integrante #{{ $history['cancellation']['actor_id'] }}.</p><p class="text-break">Motivo: {{ $history['cancellation']['reason'] }}</p>
<p>A chegada permanece registrada. Reserva, pedido e financeiro exigem revisão própria.</p>
@elseif($visit->status === 'open')
@can('cancel', $visit)
<form method="post" action="{{ route('patients.visits.cancel', [$patient->public_id, $visit->public_id]) }}" class="mb-6">
@csrf<input type="hidden" name="expected_status" value="open"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
<label class="form-label" for="reason">Motivo do cancelamento</label><textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="3" maxlength="1000" required></textarea>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
<p class="form-text">Informe apenas o motivo operacional, sem informações clínicas desnecessárias. O histórico de chegada será preservado.</p>
<button type="submit" class="btn btn-danger">Cancelar atendimento</button>
</form>
@endcan
@endif
@can('viewAny', [\App\Domain\Executions\Models\ExamExecution::class, $visit])<a class="btn btn-primary mb-3" href="{{ route('patients.visits.executions.index', [$patient->public_id, $visit->public_id]) }}">Tentativas dos exames</a>@endcan
<a class="btn btn-label-primary mb-3" href="{{ route('patients.appointments.show', [$patient->public_id, $visit->appointment->public_id]) }}">Consultar reserva</a>
<a class="btn btn-label-secondary mb-3" href="{{ route('patients.orders.show', [$patient->public_id, $visit->order->public_id]) }}">Consultar pedido</a>
</div></section>
@endsection
