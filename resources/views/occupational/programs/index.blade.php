@extends('layouts.app')
@section('title', 'Programas ocupacionais')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Programas ocupacionais</h1><p class="text-body-secondary">{{ $company->name }} · Referências e versões informadas pela clínica</p></div>
<section class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">Versões registradas</h2>@can('create', [\App\Domain\Occupational\Models\OccupationalProgram::class, $company])<a class="btn btn-primary" href="{{ route('companies.programs.create', $company->public_id) }}">Registrar versão</a>@endcan</div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Referências de programa da empresa</caption><thead><tr><th scope="col">Versão</th><th scope="col">Início</th><th scope="col">Fim</th><th scope="col">Fonte</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($programs as $program)<tr><td>{{ $program->version }}</td><td>{{ $program->valid_from?->format('d/m/Y') ?? 'Não informado' }}</td><td>{{ $program->valid_until?->format('d/m/Y') ?? 'Não informado' }}</td><td class="text-break">{{ $program->source_reference }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('companies.programs.show', [$company->public_id, $program->id]) }}">Consultar</a></td></tr>@empty<tr><td colspan="5" class="text-center text-body-secondary py-8">Nenhuma versão registrada.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $programs->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('companies.show', $company->public_id) }}">Voltar à empresa</a></div></section>
@endsection
