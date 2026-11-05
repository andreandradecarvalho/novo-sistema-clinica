@extends('layouts.app')
@section('title', 'Nova reserva diária')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Nova reserva diária</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card"><h2 class="card-header h5">Reserva por unidade e data</h2><div class="card-body">
<p>A reserva diária não define horário, sala ou capacidade da unidade. Não abre atendimento nem gera cobrança.</p>
<form method="post" action="{{ route('patients.appointments.store', $patient->public_id) }}">
@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
@error('idempotency_key')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
<div class="row">
<div class="col-md-6 mb-6"><label class="form-label" for="unit_id">Unidade</label><select class="form-select @error('unit_id') is-invalid @enderror" name="unit_id" id="unit_id" required><option value="">Selecione uma unidade</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</select>@error('unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-6 mb-6"><label class="form-label" for="scheduled_date">Data da reserva</label><input type="date" class="form-control @error('scheduled_date') is-invalid @enderror" name="scheduled_date" id="scheduled_date" min="1000-01-01" max="9999-12-31" value="{{ old('scheduled_date') }}" required>@error('scheduled_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
</div>
<button type="submit" class="btn btn-primary" @disabled($units->isEmpty())>Registrar reserva</button>
<a class="btn btn-label-secondary" href="{{ route('patients.appointments.index', $patient->public_id) }}">Voltar às reservas</a>
</form>
<div class="mt-6">{{ $units->links() }}</div><p class="form-text">Somente unidades ativas autorizadas nesta página. Ao trocar de página, preencha a reserva novamente.</p>
</div></section>
@endsection
