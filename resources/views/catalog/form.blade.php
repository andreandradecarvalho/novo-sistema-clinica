@extends('layouts.app')
@section('title', $examType->exists ? 'Editar exame' : 'Novo exame')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $examType->exists ? 'Editar exame' : 'Novo exame' }}</h1><p class="text-body-secondary">Configuração do catálogo desta clínica.</p></div>
<section class="card">
    <h2 class="card-header h5">Dados do exame</h2>
    <div class="card-body">
        <form method="post" action="{{ $examType->exists ? route('exam-types.update', $examType->public_id) : route('exam-types.store') }}">
            @csrf
            @if($examType->exists) @method('PUT') @endif
            <div class="row">
                <div class="col-md-4"><x-input name="code" label="Código" :value="old('code', $examType->code)" maxlength="20" /></div>
                <div class="col-md-8"><x-input name="name" label="Nome" :value="old('name', $examType->name)" maxlength="120" /></div>
                <div class="col-md-6"><x-input name="default_duration_min" label="Duração prevista (minutos, opcional)" type="number" :value="old('default_duration_min', $examType->default_duration_min)" :required="false" min="1" max="65535" step="1" /></div>
                <div class="col-md-6"><x-input name="price" label="Preço de referência (opcional)" type="number" :value="old('price', $examType->price)" :required="false" min="0" max="9999999999.99" step="0.01" /></div>
                <div class="col-md-6 mb-6">
                    <label class="form-label" for="requires_fasting">Exige jejum?</label>
                    <select id="requires_fasting" name="requires_fasting" class="form-select @error('requires_fasting') is-invalid @enderror" required aria-describedby="fasting-help">
                        <option value="">Selecione</option>
                        <option value="1" @selected((string) old('requires_fasting', $examType->exists ? (int) $examType->requires_fasting : '') === '1')>Sim</option>
                        <option value="0" @selected((string) old('requires_fasting', $examType->exists ? (int) $examType->requires_fasting : '') === '0')>Não</option>
                    </select>
                    @error('requires_fasting')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div id="fasting-help" class="form-text">Preencha conforme orientação aprovada pelo responsável técnico.</div>
                </div>
                <div class="col-md-6"><x-input name="ordem" label="Ordem de exibição" type="number" :value="old('ordem', $examType->ordem ?? 0)" min="0" max="65535" step="1" /></div>
            </div>
            <x-active-select :active="$examType->exists ? $examType->is_active : true" />
            <div class="d-flex gap-3"><button class="btn btn-primary" type="submit">Salvar exame</button><a class="btn btn-label-secondary" href="{{ route('exam-types.index') }}">Cancelar</a></div>
        </form>
    </div>
</section>
@endsection
