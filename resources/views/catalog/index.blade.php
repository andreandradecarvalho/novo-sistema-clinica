@extends('layouts.app')
@section('title', 'Catálogo de exames')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Catálogo de exames</h1><p class="text-body-secondary">Exames configurados nesta clínica.</p></div>
<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h2 class="h5 mb-0">{{ $exams->total() }} exames</h2>
        @can('create', \App\Domain\Catalog\Models\ExamType::class)<a class="btn btn-primary" href="{{ route('exam-types.create') }}">Novo exame</a>@endcan
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <caption class="visually-hidden">Catálogo de exames desta clínica</caption>
        <thead><tr><th scope="col">Código</th><th scope="col">Nome</th><th scope="col">Duração prevista</th><th scope="col">Preço de referência</th><th scope="col">Situação</th><th scope="col">Ações</th></tr></thead>
        <tbody>
        @forelse($exams as $exam)
            <tr><td>{{ $exam->code }}</td><td><a href="{{ route('exam-types.show', $exam->public_id) }}">{{ $exam->name }}</a></td><td>{{ $exam->default_duration_min === null ? 'Não informada' : $exam->default_duration_min.' min' }}</td><td>{{ $exam->price ?? 'Não informado' }}</td><td><span @class(['badge', 'bg-label-success' => $exam->is_active, 'bg-label-secondary' => !$exam->is_active])>{{ $exam->is_active ? 'Ativo' : 'Inativo' }}</span></td><td><a class="btn btn-sm btn-label-primary" href="{{ route('exam-types.show', $exam->public_id) }}">Consultar</a></td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-body-secondary py-8">Nenhum exame cadastrado.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-body border-top">{{ $exams->links('pagination::bootstrap-5') }}</div>
</section>
@endsection
