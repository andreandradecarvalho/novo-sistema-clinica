@extends('layouts.app')
@section('title', 'Empresas')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Empresas clientes</h1><p class="text-body-secondary">Empregadores e estabelecimentos cadastrados nesta clínica.</p></div>
<section class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">{{ $companies->total() }} empresas</h2>@can('create', \App\Domain\Companies\Models\Company::class)<a class="btn btn-primary" href="{{ route('companies.create') }}">Nova empresa</a>@endcan</div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Empresas clientes desta clínica</caption><thead><tr><th scope="col">Nome</th><th scope="col">CNPJ</th><th scope="col">Situação</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($companies as $company)<tr><td><a href="{{ route('companies.show', $company->public_id) }}">{{ $company->name }}</a></td><td>{{ $company->cnpj ?? 'Não informado' }}</td><td><span @class(['badge', 'bg-label-success' => $company->is_active, 'bg-label-secondary' => !$company->is_active])>{{ $company->is_active ? 'Ativa' : 'Inativa' }}</span></td><td><a class="btn btn-sm btn-label-primary" href="{{ route('companies.show', $company->public_id) }}">Consultar</a></td></tr>@empty<tr><td colspan="4" class="text-center text-body-secondary py-8">Nenhuma empresa cadastrada.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $companies->links('pagination::bootstrap-5') }}</div></section>
@endsection
