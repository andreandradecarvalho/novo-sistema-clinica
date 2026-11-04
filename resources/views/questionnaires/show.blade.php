@extends('layouts.app')
@section('title', 'Perguntas do grupo')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $group->name }}</h1><p class="text-body-secondary">{{ $group->code }} · Todas as versões cadastradas</p></div>
<section class="card mb-6"><h2 class="card-header h5">Perguntas e histórico</h2>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <caption class="visually-hidden">Versões das perguntas deste grupo</caption>
        <thead><tr><th scope="col">Código</th><th scope="col">Enunciado</th><th scope="col">Versão</th><th scope="col">Ações</th></tr></thead>
        <tbody>@forelse($questions as $question)
            <tr><td>{{ $question->code }}</td><td class="text-break">{{ $question->label }}</td><td>{{ $question->version }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('questionnaires.questions.show', [$group->id, $question->id]) }}">Consultar versão</a></td></tr>
        @empty<tr><td colspan="4" class="text-center text-body-secondary py-8">Nenhuma pergunta cadastrada.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="card-body border-top">{{ $questions->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('questionnaires.index') }}">Voltar aos grupos</a></div>
</section>
@can('publish', $group)
<section class="card"><h2 class="card-header h5">Nova pergunta de texto livre</h2><div class="card-body">
    <p class="text-body-secondary">Cadastre conteúdo aprovado pelo responsável técnico. Esta tela configura perguntas; não registra respostas de pacientes.</p>
    <form method="post" action="{{ route('questionnaires.questions.store', $group->id) }}">@csrf
        @foreach(['code' => ['Código', 60], 'label' => ['Enunciado', 255]] as $field => [$label, $limit])
            @php($value = old($field))
            <x-input :name="$field" :label="$label" :value="is_string($value) ? $value : ''" :maxlength="$limit" />
        @endforeach
        @php($order = old('ordem', '0'))
        <x-input name="ordem" label="Ordem de exibição" type="number" :value="is_scalar($order) ? (string) $order : '0'" min="0" max="65535" />
        <button type="submit" class="btn btn-primary">Cadastrar pergunta</button>
    </form>
</div></section>
@endcan
@endsection
