@extends('layouts.app')
@section('title', $preparation->exists ? 'Editar preparo' : 'Novo preparo')
@section('content')
@php
    $title = old('title', $preparation->title);
    $instructions = old('instructions', $preparation->instructions);
@endphp
<div class="page-heading"><h1 class="h4 mb-1">{{ $preparation->exists ? 'Editar preparo' : 'Novo preparo' }}</h1><p class="text-body-secondary">{{ $examType->name }}</p></div>
<section class="card">
    <h2 class="card-header h5">Orientações de preparo</h2>
    <div class="card-body">
        <form method="post" action="{{ $preparation->exists ? route('exam-types.preparations.update', [$examType->public_id, $preparation->id]) : route('exam-types.preparations.store', $examType->public_id) }}">
            @csrf
            @if($preparation->exists) @method('PUT') @endif
            <x-input name="title" label="Título" :value="is_string($title) ? $title : ''" maxlength="150" />
            <div class="mb-6">
                <label for="instructions" class="form-label">Instruções</label>
                <textarea id="instructions" name="instructions" rows="10" maxlength="16000" class="form-control @error('instructions') is-invalid @enderror" required aria-describedby="instructions-help @error('instructions') instructions-error @enderror" @error('instructions') aria-invalid="true" @enderror>{{ is_string($instructions) ? $instructions : '' }}</textarea>
                <div id="instructions-help" class="form-text">Use orientações aprovadas pelo responsável técnico. O texto preserva as quebras de linha.</div>
                @error('instructions')<div id="instructions-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Salvar preparo</button><a class="btn btn-label-secondary" href="{{ route('exam-types.preparations.index', $examType->public_id) }}">Cancelar</a></div>
        </form>
    </div>
</section>
@endsection
