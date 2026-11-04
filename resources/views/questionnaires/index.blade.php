@extends('layouts.app')
@section('title', 'Questionários')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Questionários</h1><p class="text-body-secondary">Grupos de perguntas para configuração da clínica.</p></div>
<section class="card mb-6">
    <h2 class="card-header h5">Grupos cadastrados</h2>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <caption class="visually-hidden">Grupos de perguntas desta clínica</caption>
        <thead><tr><th scope="col">Código</th><th scope="col">Nome</th><th scope="col">Ordem</th><th scope="col">Ações</th></tr></thead>
        <tbody>@forelse($groups as $group)
            <tr><td>{{ $group->code }}</td><td class="text-break">{{ $group->name }}</td><td>{{ $group->ordem }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('questionnaires.show', $group->id) }}">Consultar</a></td></tr>
        @empty<tr><td colspan="4" class="text-center text-body-secondary py-8">Nenhum grupo cadastrado.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="card-body border-top">{{ $groups->links('pagination::bootstrap-5') }}</div>
</section>
@can('create', \App\Domain\Catalog\Models\HealthQuestionGroup::class)
<section class="card"><h2 class="card-header h5">Novo grupo</h2><div class="card-body">
    <form method="post" action="{{ route('questionnaires.store') }}">@csrf
        <div class="row">
            @foreach(['code' => ['Código', 40], 'name' => ['Nome', 120]] as $field => [$label, $limit])
                @php($value = old($field))
                <div class="col-md-6"><x-input :name="$field" :label="$label" :value="is_string($value) ? $value : ''" :maxlength="$limit" /></div>
            @endforeach
        </div>
        @php($order = old('ordem', '0'))
        <x-input name="ordem" label="Ordem de exibição" type="number" :value="is_scalar($order) ? (string) $order : '0'" min="0" max="65535" />
        <button type="submit" class="btn btn-primary">Cadastrar grupo</button>
    </form>
</div></section>
@endcan
@endsection
