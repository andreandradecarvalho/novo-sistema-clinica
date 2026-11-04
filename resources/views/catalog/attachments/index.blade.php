@extends('layouts.app')
@section('title', 'Anexos de orientação')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Anexos de orientação</h1><p class="text-body-secondary">{{ $examType->name }}</p></div>
<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">{{ $attachments->total() }} anexos</h2>@can('create', [\App\Domain\Catalog\Models\ExamAttachment::class, $examType])<a class="btn btn-primary" href="{{ route('exam-types.attachments.create', $examType->public_id) }}">Novo anexo</a>@endcan</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <caption class="visually-hidden">Arquivos de orientação deste exame</caption>
        <thead><tr><th scope="col">Descrição</th><th scope="col">Ações</th></tr></thead>
        <tbody>@forelse($attachments as $attachment)<tr><td class="text-break">{{ $attachment->label }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('exam-types.attachments.download', [$examType->public_id, $attachment->id]) }}">Baixar anexo</a></td></tr>@empty<tr><td colspan="2" class="text-center text-body-secondary py-8">Nenhum anexo cadastrado.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="card-body border-top">{{ $attachments->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('exam-types.show', $examType->public_id) }}">Voltar ao exame</a></div>
</section>
@endsection
