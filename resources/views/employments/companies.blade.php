@extends('layouts.app')
@section('title', 'Selecionar empregador')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Novo vínculo empregatício</h1><p class="text-body-secondary">{{ $patient->name }} · Selecione a empresa empregadora.</p></div>
<section class="card"><h2 class="card-header h5">Empresas ativas</h2><div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Empresas disponíveis nesta clínica</caption><thead><tr><th scope="col">Empresa</th><th scope="col">Ação</th></tr></thead><tbody>
@forelse($companies as $company)<tr><td>{{ $company->name }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('patients.employments.registration', [$patient->public_id, $company->public_id]) }}">Selecionar</a></td></tr>@empty<tr><td colspan="2" class="text-center py-8 text-body-secondary">Nenhuma empresa ativa disponível.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $companies->links('pagination::bootstrap-5') }}</div></section>
<a class="btn btn-label-secondary mt-6" href="{{ route('patients.employments.index', $patient->public_id) }}">Voltar aos vínculos</a>
@endsection
