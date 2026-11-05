@extends('layouts.app')
@section('title', 'Pedidos de exames')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Pedidos de exames</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">Pedidos deste paciente</h2>@can('create', [\App\Domain\Orders\Models\ExamOrder::class, $patient])<a class="btn btn-primary" href="{{ route('patients.orders.create', $patient->public_id) }}">Novo pedido</a>@endcan
    @can('chooseCompany', [\App\Domain\Orders\Models\ExamOrder::class, $patient])<a class="btn btn-label-primary" href="{{ route('patients.orders.payers', $patient->public_id) }}">Novo pedido empresarial</a>@endcan</div>
    <div class="table-responsive"><table class="table"><caption class="visually-hidden">Pedidos deste paciente</caption><thead><tr><th scope="col">Referência</th><th scope="col">Situação</th><th scope="col">Ações</th></tr></thead><tbody>
    @forelse($orders as $order)
        <tr><td>{{ $order->public_id }}</td><td>{{ ['draft' => 'Rascunho', 'authorized' => 'Autorizado', 'completed' => 'Exames concluídos', 'canceled' => 'Cancelado'][$order->status] ?? $order->status }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('patients.orders.show', [$patient->public_id, $order->public_id]) }}">Consultar</a></td></tr>
    @empty
        <tr><td colspan="3">Nenhum pedido registrado.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="card-body border-top">{{ $orders->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('patients.show', $patient->public_id) }}">Voltar ao paciente</a></div>
</section>
@endsection
