@extends('layouts.app')
@section('title', $patient->exists ? 'Editar paciente' : 'Novo paciente')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $patient->exists ? 'Editar paciente' : 'Novo paciente' }}</h1><p class="text-body-secondary">Informe somente os dados necessários à identificação.</p></div>
<section class="card"><h2 class="card-header h5">Identificação e contato</h2><div class="card-body">
<form method="post" action="{{ $patient->exists ? route('patients.update', $patient->public_id) : route('patients.store') }}" autocomplete="off">
    @csrf @if($patient->exists) @method('PUT') @endif
    <div class="row"><div class="col-md-8"><x-input name="name" label="Nome completo" :value="$patient->name" maxlength="180" /></div><div class="col-md-4"><x-input name="birth_date" label="Data de nascimento" type="date" :value="$patient->birth_date?->format('Y-m-d')" :required="false" /></div></div>
    <div class="row"><div class="col-md-6"><x-input name="cpf" label="CPF (opcional)" :required="false" maxlength="14" inputmode="numeric" /><p class="small text-body-secondary">{{ $patient->exists ? 'Deixe em branco para preservar o CPF atual. Preencha apenas para corrigir.' : 'É possível cadastrar sem CPF e completar a identificação depois.' }}</p></div><div class="col-md-6"><x-input name="mobile_phone" label="Celular (opcional)" :value="$patient->mobile_phone" :required="false" maxlength="20" type="tel" /></div></div>
    <x-input name="email" label="E-mail (opcional)" type="email" :value="$patient->email" :required="false" maxlength="180" />
    <div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Salvar cadastro</button><a href="{{ route('patients.index') }}" class="btn btn-label-secondary">Cancelar</a></div>
</form></div></section>
@endsection
