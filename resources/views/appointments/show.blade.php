@extends('layouts.app')
@section('title', 'Reserva diária')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Reserva diária</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card"><h2 class="card-header h5">{{ $appointment->public_id }}</h2><div class="card-body">
<dl class="row"><dt class="col-sm-4">Unidade no registro</dt><dd class="col-sm-8">{{ $history['unit_name'] }}</dd><dt class="col-sm-4">Data</dt><dd class="col-sm-8">{{ $appointment->scheduled_date->format('d/m/Y') }}</dd><dt class="col-sm-4">Situação</dt><dd class="col-sm-8">{{ ['pre_agendado' => 'Pré-agendado', 'confirmado' => 'Confirmado', 'checked_in' => 'Check-in realizado', 'cancelado' => 'Cancelado'][$appointment->status] ?? $appointment->status }}</dd></dl>
@error('visit')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@error('appointment')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@error('idempotency_key')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@error('expected_status')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@if(isset($history['cancellation']))<p>Cancelada em {{ $history['cancellation']['occurred_at'] }} por integrante #{{ $history['cancellation']['actor_id'] }}.</p><p class="text-break">Motivo: {{ $history['cancellation']['reason'] }}</p>@endif
@if(isset($history['confirmation']))<p>Confirmação manual registrada em {{ $history['confirmation']['occurred_at'] }} por integrante #{{ $history['confirmation']['actor_id'] }}.</p>@endif
@if($appointment->status === 'pre_agendado')
    @can('confirm', $appointment)
    <form method="post" action="{{ route('patients.appointments.confirm', [$patient->public_id, $appointment->public_id]) }}" class="mb-6">
        @csrf<input type="hidden" name="expected_status" value="pre_agendado"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <button type="submit" class="btn btn-primary">Registrar confirmação manual</button>
    </form>
    @endcan
@endif
@if($appointment->status === 'confirmado')
    @can('checkIn', [\App\Domain\Visits\Models\Visit::class, $appointment])<a class="btn btn-primary mb-6" href="{{ route('patients.appointments.check-in.create', [$patient->public_id, $appointment->public_id]) }}">Abrir check-in</a>@endcan
@endif
@if(isset($history['check_in']))
    <p>Check-in em {{ $history['check_in']['occurred_at'] }} por integrante #{{ $history['check_in']['actor_id'] }}.</p>
    @can('visits.view')<a class="btn btn-label-primary mb-6" href="{{ route('patients.visits.show', [$patient->public_id, $history['check_in']['visit_public_id']]) }}">Consultar atendimento</a>@endcan
@endif
@if(in_array($appointment->status, ['pre_agendado', 'confirmado'], true))
@can('cancel', $appointment)
<form method="post" action="{{ route('patients.appointments.cancel', [$patient->public_id, $appointment->public_id]) }}" class="mb-6">
@csrf<input type="hidden" name="expected_status" value="{{ $appointment->status }}"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
<label class="form-label" for="reason">Motivo do cancelamento</label><textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="3" maxlength="1000" required></textarea>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
<p class="form-text">Informe apenas o motivo operacional, sem informações clínicas desnecessárias.</p><button type="submit" class="btn btn-danger">Cancelar reserva</button>
</form>
@endcan
@endif
<a class="btn btn-label-secondary" href="{{ route('patients.appointments.index', $patient->public_id) }}">Voltar às reservas</a>
</div></section>
@endsection
