@extends('layouts.app')
@section('title', 'Registrar versão de programa')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Registrar versão de programa</h1><p class="text-body-secondary">{{ $company->name }} · Responsável informado: {{ $professional->user->name }}</p></div>
<section class="card"><h2 class="card-header h5">Referência do programa</h2><div class="card-body">
<p>Informe a referência e o contexto fornecidos pelo responsável da clínica. Este cadastro preserva informações do programa; não gera PCMSO, PGR, protocolo ou aprovação médica.</p>
<form method="post" action="{{ route('companies.programs.store', [$company->public_id, $professional->id]) }}">@csrf
    @php($version = old('version'))
    <x-input name="version" label="Versão informada no documento de origem" :value="is_string($version) ? $version : ''" maxlength="40" />
    <div class="row">
        @foreach(['valid_from' => ['Início da vigência', true], 'valid_until' => ['Fim da vigência (opcional)', false]] as $field => [$label, $required])
        @php($value = old($field))
        <div class="col-md-6"><x-input :name="$field" :label="$label" type="date" :required="$required" :value="is_string($value) ? $value : ''" /></div>
        @endforeach
    </div>
    <x-input name="source_reference" label="Referência ou origem da informação" value="" maxlength="190" />
    <div class="mb-6"><label class="form-label" for="context">Contexto informado pela clínica</label><textarea name="context" id="context" rows="8" maxlength="16000" required class="form-control @error('context') is-invalid @enderror" @error('context') aria-invalid="true" aria-describedby="context-error" @enderror></textarea>@error('context')<div id="context-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
    <p class="form-text">Não inclua dados de pacientes. Origem e contexto não são recuperados após erro de validação. Cada versão cadastrada é preservada.</p>
    <div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Registrar versão</button><a class="btn btn-label-secondary" href="{{ route('companies.programs.index', $company->public_id) }}">Cancelar</a></div>
</form></div></section>
@endsection
