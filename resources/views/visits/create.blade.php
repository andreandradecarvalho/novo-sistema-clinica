@extends('layouts.app')
@section('title', 'Check-in da reserva')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Check-in da reserva</h1><p class="text-body-secondary">{{ $patient->name }} — {{ $appointment->scheduled_date->format('d/m/Y') }}</p></div>
<section class="card"><h2 class="card-header h5">Selecionar pedido autorizado</h2><div class="card-body">
<p>O check-in abre o atendimento nesta unidade. Não inicia exames, não gera cobrança e não representa aprovação clínica.</p>
@error('visit')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
<form method="post" action="{{ route('patients.appointments.check-in.store', [$patient->public_id, $appointment->public_id]) }}">
@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}"><input type="hidden" name="expected_status" value="confirmado">
@error('idempotency_key')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@error('expected_status')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
<div class="row"><div class="col-md-8 mb-6"><label class="form-label" for="exam_order_id">Pedido deste paciente</label><select class="form-select @error('exam_order_id') is-invalid @enderror" id="exam_order_id" name="exam_order_id" required><option value="">Selecione um pedido</option>
@foreach($orders as $order)<option value="{{ $order->id }}" @selected((string) old('exam_order_id') === (string) $order->id)>{{ $order->public_id }} — {{ $order->payer_type === 'company' ? 'Empresa' : 'Particular' }}</option>@endforeach
</select>@error('exam_order_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
<button class="btn btn-primary" type="submit" @disabled($orders->isEmpty())>Registrar check-in e abrir atendimento</button>
<a class="btn btn-label-secondary" href="{{ route('patients.appointments.show', [$patient->public_id, $appointment->public_id]) }}">Voltar à reserva</a>
</form><div class="mt-6">{{ $orders->links() }}</div><p class="form-text">Pedidos autorizados e acessíveis nesta página. Ao trocar de página, faça a seleção novamente.</p>
</div></section>
@endsection
