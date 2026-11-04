@extends('layouts.app')
@section('title', 'Versão da pergunta')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Pergunta {{ $question->code }}</h1><p class="text-body-secondary">{{ $group->name }} · Versão {{ $question->version }}</p></div>
<section class="card mb-6"><h2 class="card-header h5">Conteúdo preservado</h2><div class="card-body">
    <p class="text-break">{{ $question->label }}</p>
    <p class="text-body-secondary">Tipo: texto livre. Cada alteração cria uma nova versão e preserva a anterior.</p>
    <a class="btn btn-label-secondary" href="{{ route('questionnaires.show', $group->id) }}">Voltar ao grupo</a>
</div></section>
@can('publish', $group)
<section class="card"><h2 class="card-header h5">Cadastrar nova versão</h2><div class="card-body">
    <p class="text-body-secondary">Utilize a versão mais recente como origem. O cadastro não substitui a aprovação clínica do conteúdo.</p>
    <form method="post" action="{{ route('questionnaires.questions.revise', [$group->id, $question->id]) }}">@csrf
        @php($label = old('label', $question->label))
        <x-input name="label" label="Novo enunciado" :value="is_string($label) ? $label : ''" maxlength="255" />
        <button type="submit" class="btn btn-primary">Cadastrar nova versão</button>
    </form>
</div></section>
@endcan
@endsection
