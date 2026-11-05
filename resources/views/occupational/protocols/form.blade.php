@extends('layouts.app')
@section('title', $protocol ? 'Nova versão de protocolo' : 'Novo protocolo')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $protocol ? 'Nova versão de protocolo' : 'Novo protocolo' }}</h1><p class="text-body-secondary">Programa {{ $program->version }}{{ $protocol ? ' · '.$protocol->code : '' }}</p></div>
<section class="card"><h2 class="card-header h5">Contexto e exames informados pela clínica</h2><div class="card-body">
<form method="post" action="{{ $protocol ? route('programs.protocols.replace', [$program->id, $protocol->id]) : route('programs.protocols.store', $program->id) }}">@csrf
    @if(!$protocol)<x-input name="code" label="Código do protocolo" value="" maxlength="60" />@endif
    <div class="row">@foreach(['valid_from' => ['Início da vigência', true], 'valid_until' => ['Fim da vigência (opcional)', false]] as $field => [$label, $required])
        @php($value = old($field, $protocol?->{$field}?->format('Y-m-d')))
        <div class="col-md-6"><x-input :name="$field" :label="$label" type="date" :required="$required" :value="is_string($value) ? $value : ''" /></div>
    @endforeach</div>
    <x-input name="source_reference" label="Referência da fonte fornecida pela clínica" :value="$rules['source_reference'] ?? ''" maxlength="190" />
    <x-input name="job_title" label="Função à qual se aplica (opcional)" :value="$rules['job_title'] ?? ''" maxlength="120" :required="false" />
    <div class="mb-6"><label for="risk_context" class="form-label">Contexto de riscos informado pela clínica</label><textarea name="risk_context" id="risk_context" rows="6" maxlength="4000" required class="form-control @error('risk_context') is-invalid @enderror" @error('risk_context') aria-invalid="true" aria-describedby="risk-context-error" @enderror>{{ $rules['risk_context'] ?? '' }}</textarea>@error('risk_context')<div id="risk-context-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
    <div class="mb-6"><label for="exam_codes" class="form-label">Códigos dos exames, um por linha</label><textarea name="exam_codes" id="exam_codes" rows="6" maxlength="4000" required class="form-control @error('exam_codes') is-invalid @enderror" aria-describedby="exams-help @error('exam_codes') exams-error @enderror" @error('exam_codes') aria-invalid="true" @enderror>{{ implode("\n", array_column($rules['exams'] ?? [], 'code')) }}</textarea><div id="exams-help" class="form-text">Selecione de 1 a 100 exames ativos do catálogo da clínica. Nenhum exame ou periodicidade é sugerido automaticamente.</div>@error('exam_codes')<div id="exams-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
    <p class="form-text">Não inclua dados de pacientes. Cada envio preserva a versão anterior e exige aprovação própria. Ao corrigir erros, confira novamente todos os campos; uma nova versão parte dos dados da origem.</p>
    <div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Cadastrar versão pendente</button><a class="btn btn-label-secondary" href="{{ route('programs.protocols.index', $program->id) }}">Cancelar</a></div>
</form></div></section>
@endsection
