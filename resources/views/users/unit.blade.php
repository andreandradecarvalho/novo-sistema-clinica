@extends('layouts.app')
@section('title', 'Unidade do integrante')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Unidade do integrante</h1><p class="text-body-secondary">{{ $subject->name }} · Unidade atual: {{ $subject->unit?->name ?? 'Sem unidade atribuída' }}</p></div>
<section class="card"><h2 class="card-header h5">Atribuição de unidade clínica</h2><div class="card-body">
<p>A alteração encerra as sessões do integrante e exige novo login. Papéis e permissões permanecem os mesmos.</p>
<form method="post" action="{{ route('users.unit.update', $subject->public_id) }}">@csrf @method('PUT')
<div class="mb-6"><label for="unit_id" class="form-label">Unidade clínica</label><select id="unit_id" name="unit_id" class="form-select" aria-describedby="unit_help"><option value="">Sem unidade atribuída</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) $subject->unit_id === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</select><div class="form-text" id="unit_help">Somente unidades ativas desta clínica. A ausência de unidade não concede acesso a todas as unidades.</div></div>
@if($subject->unit && !$subject->unit->is_active)<p class="alert alert-warning">A unidade atual está inativa. Selecione outra unidade ou remova a atribuição explicitamente.</p>@endif
<div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Salvar atribuição</button><a class="btn btn-label-secondary" href="{{ route('users.show', $subject->public_id) }}">Cancelar</a></div>
</form></div></section>
@endsection
