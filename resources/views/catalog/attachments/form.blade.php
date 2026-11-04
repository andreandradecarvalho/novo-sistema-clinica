@extends('layouts.app')
@section('title', 'Novo anexo de orientação')
@section('content')
@php($label = old('label'))
<div class="page-heading"><h1 class="h4 mb-1">Novo anexo de orientação</h1><p class="text-body-secondary">{{ $examType->name }}</p></div>
<section class="card"><h2 class="card-header h5">Arquivo de orientação</h2><div class="card-body">
    <form method="post" enctype="multipart/form-data" action="{{ route('exam-types.attachments.store', $examType->public_id) }}">
        @csrf
        <x-input name="label" label="Descrição" :value="is_string($label) ? $label : ''" maxlength="150" />
        <div class="mb-6"><label for="file" class="form-label">Arquivo</label><input id="file" name="file" type="file" accept=".pdf,.png,.jpg,.jpeg" class="form-control @error('file') is-invalid @enderror" required aria-describedby="file-help @error('file') file-error @enderror" @error('file') aria-invalid="true" @enderror><div id="file-help" class="form-text">PDF, PNG ou JPEG, até 2 MB. Envie apenas orientações gerais aprovadas pela clínica, sem dados de pacientes.</div>@error('file')<div id="file-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
        <div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Salvar anexo</button><a class="btn btn-label-secondary" href="{{ route('exam-types.attachments.index', $examType->public_id) }}">Cancelar</a></div>
    </form>
</div></section>
@endsection
