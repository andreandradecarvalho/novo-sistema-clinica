@extends('layouts.app')
@section('title', 'Empresa pagadora')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Empresa pagadora</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card"><h2 class="card-header h5">Escolha a empresa responsável pelo pagamento</h2><div class="card-body"><p>O pedido usará os preços do catálogo no registro. A escolha da pagadora não cria vínculo empregatício nem concede acesso a dados do paciente.</p></div>
<div class="table-responsive"><table class="table"><caption class="visually-hidden">Empresas ativas desta clínica</caption><thead><tr><th scope="col">Empresa</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($companies as $company)<tr><td class="text-break">{{ $company->name }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('patients.orders.company.create', [$patient->public_id, $company->public_id]) }}">Selecionar pagadora</a></td></tr>@empty<tr><td colspan="2">Nenhuma empresa ativa disponível.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $companies->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('patients.orders.index', $patient->public_id) }}">Voltar aos pedidos</a></div></section>
@endsection
