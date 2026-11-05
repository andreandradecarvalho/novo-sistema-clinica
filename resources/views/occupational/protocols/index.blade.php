@extends('layouts.app')
@section('title', 'Protocolos ocupacionais')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Protocolos ocupacionais</h1><p class="text-body-secondary">Programa {{ $program->version }} · Todas as versões cadastradas</p></div>
<section class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">Versões e aprovação</h2>@can('create', [\App\Domain\Occupational\Models\OccupationalProtocol::class, $program])<a class="btn btn-primary" href="{{ route('programs.protocols.create', $program->id) }}">Novo protocolo</a>@endcan</div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Protocolos deste programa</caption><thead><tr><th scope="col">Código</th><th scope="col">Versão</th><th scope="col">Aprovação</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($protocols as $protocol)<tr><td>{{ $protocol->code }}</td><td>{{ $protocol->version }}</td><td>{{ $protocol->approved_at ? 'Aprovada' : 'Pendente' }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('programs.protocols.show', [$program->id, $protocol->id]) }}">Consultar versão</a></td></tr>@empty<tr><td colspan="4" class="text-center text-body-secondary py-8">Nenhum protocolo cadastrado.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $protocols->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('companies.programs.show', [$program->company->public_id, $program->id]) }}">Voltar ao programa</a></div></section>
@endsection
