@extends('layouts.app')
@section('title', 'Preparos do exame')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Preparos do exame</h1><p class="text-body-secondary">{{ $examType->name }}</p></div>
<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h2 class="h5 mb-0">{{ $preparations->total() }} orientações</h2>
        @can('create', [\App\Domain\Catalog\Models\ExamPreparation::class, $examType])<a class="btn btn-primary" href="{{ route('exam-types.preparations.create', $examType->public_id) }}">Novo preparo</a>@endcan
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <caption class="visually-hidden">Preparos deste exame</caption>
        <thead><tr><th scope="col">Título</th><th scope="col">Ações</th></tr></thead>
        <tbody>
        @forelse($preparations as $preparation)
            <tr><td class="text-break">{{ $preparation->title }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('exam-types.preparations.show', [$examType->public_id, $preparation->id]) }}">Consultar</a></td></tr>
        @empty
            <tr><td colspan="2" class="text-center text-body-secondary py-8">Nenhum preparo cadastrado.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body border-top">{{ $preparations->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('exam-types.show', $examType->public_id) }}">Voltar ao exame</a></div>
</section>
@endsection
