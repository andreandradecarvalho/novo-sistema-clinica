@extends('layouts.app')
@section('title', 'Selecionar responsável')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Selecionar responsável informado</h1><p class="text-body-secondary">{{ $company->name }}</p></div>
<section class="card"><h2 class="card-header h5">Profissionais da clínica</h2>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Profissionais com integrante ativo</caption><thead><tr><th scope="col">Nome</th><th scope="col">Conselho</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($professionals as $professional)<tr><td>{{ $professional->user->name }}</td><td>{{ $professional->council ?? 'Não informado' }} {{ $professional->council_uf }} {{ $professional->council_number }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('companies.programs.registration', [$company->public_id, $professional->id]) }}">Selecionar</a></td></tr>@empty<tr><td colspan="3" class="text-center text-body-secondary py-8">Nenhum profissional disponível.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $professionals->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('companies.programs.index', $company->public_id) }}">Voltar</a></div></section>
@endsection
