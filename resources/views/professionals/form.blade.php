@extends('layouts.app')
@section('title', $professional->exists ? 'Editar profissional' : 'Novo profissional')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $professional->exists ? 'Editar profissional' : 'Novo profissional' }}</h1><p class="text-body-secondary">{{ $candidate?->name ?? $professional->user?->name ?? 'Integrante indisponível' }}</p></div>
<section class="card"><h2 class="card-header h5">Registro profissional</h2><div class="card-body"><p class="text-body-secondary">Informações cadastrais. A conferência da habilitação é feita pela clínica.</p>
<form method="post" action="{{ $professional->exists ? route('professionals.update', $professional->id) : route('professionals.store') }}">
@csrf @if($professional->exists) @method('PUT') @else <input type="hidden" name="user_id" value="{{ $candidate->id }}"> @endif
<div class="row"><div class="col-md-4"><x-input name="council" label="Conselho (opcional)" :value="$professional->council" :required="false" maxlength="10" autocapitalize="characters" /></div><div class="col-md-4 mb-6"><label class="form-label" for="council_uf">UF do conselho</label><select class="form-select" id="council_uf" name="council_uf"><option value="">Não informada</option>@foreach(\App\Domain\Shared\Enums\BrazilianState::cases() as $state)<option value="{{ $state->value }}" @selected(old('council_uf', $professional->council_uf) === $state->value)>{{ $state->value }}</option>@endforeach</select>@error('council_uf')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div><div class="col-md-4"><x-input name="council_number" label="Número do registro" :value="$professional->council_number" :required="false" maxlength="20" /></div></div>
<p class="small text-body-secondary">Ao informar o registro, preencha conselho, UF e número.</p>
<x-input name="specialty" label="Especialidade (opcional)" :value="$professional->specialty" :required="false" maxlength="60" />
<div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Salvar profissional</button><a class="btn btn-label-secondary" href="{{ route('professionals.index') }}">Cancelar</a></div>
</form></div></section>
@endsection
