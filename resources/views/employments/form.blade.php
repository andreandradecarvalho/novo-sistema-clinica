@extends('layouts.app')
@section('title', $employment->exists ? 'Nova versão de vínculo' : 'Novo vínculo empregatício')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $employment->exists ? 'Nova versão de vínculo' : 'Novo vínculo empregatício' }}</h1><p class="text-body-secondary">{{ $patient->name }} · {{ $company->name }}</p></div>
<section class="card"><h2 class="card-header h5">Contexto do vínculo</h2><div class="card-body">
@if($employment->exists)<p class="alert alert-info">A versão anterior será encerrada no dia anterior ao novo início. Função, setor e estabelecimento anteriores serão preservados. Esta operação não registra desligamento.</p>@endif
<form method="post" action="{{ $employment->exists ? route('patients.employments.replace', [$patient->public_id, $employment->id]) : route('patients.employments.store', [$patient->public_id, $company->public_id]) }}">
@csrf
<div class="mb-6"><label for="company_unit_id" class="form-label">Estabelecimento (opcional)</label><select class="form-select" id="company_unit_id" name="company_unit_id"><option value="">Não informado</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) $employment->company_unit_id === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</select>@error('company_unit_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="row"><div class="col-md-6"><x-input name="registration_number" label="Matrícula (opcional)" :value="$employment->registration_number" :required="false" maxlength="50" /></div><div class="col-md-6"><x-input name="starts_on" label="{{ $employment->exists ? 'Início da nova versão' : 'Início do vínculo' }}" type="date" /></div></div>
<x-input name="job_title" label="Função (opcional)" :value="$employment->job_title" :required="false" maxlength="150" />
<x-input name="department" label="Setor (opcional)" :value="$employment->department" :required="false" maxlength="150" />
<div class="d-flex gap-3"><button type="submit" class="btn btn-primary">{{ $employment->exists ? 'Registrar nova versão' : 'Salvar vínculo' }}</button><a class="btn btn-label-secondary" href="{{ route('patients.employments.index', $patient->public_id) }}">Cancelar</a></div>
</form></div></section>
@endsection
