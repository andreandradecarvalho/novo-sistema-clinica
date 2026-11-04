@extends('layouts.app')
@section('title', 'Preparo do exame')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1 text-break">{{ $preparation->title }}</h1><p class="text-body-secondary">{{ $examType->name }}</p></div>
<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">Instruções de preparo</h2>@can('update', $preparation)<a class="btn btn-primary" href="{{ route('exam-types.preparations.edit', [$examType->public_id, $preparation->id]) }}">Editar preparo</a>@endcan</div>
    <div class="card-body">
        <div class="preparation-instructions mb-6">{{ $preparation->instructions }}</div>
        <a class="btn btn-label-secondary" href="{{ route('exam-types.preparations.index', $examType->public_id) }}">Voltar aos preparos</a>
    </div>
</section>
@endsection
