@extends('layouts.app')
@section('title', 'Pedido de exames')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Pedido de exames</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card"><h2 class="card-header h5">{{ $order->public_id }}</h2><div class="card-body"><dl class="row"><dt class="col-sm-4">Situação</dt><dd class="col-sm-8">{{ ['draft' => 'Rascunho', 'authorized' => 'Autorizado', 'in_progress' => 'Em execução', 'completed' => 'Exames concluídos', 'canceled' => 'Cancelado'][$order->status] ?? $order->status }}</dd><dt class="col-sm-4">Pagador</dt><dd class="col-sm-8">{{ $order->payer_type === 'company' ? 'Empresa' : 'Particular' }} — {{ $history['payer_name'] }}</dd>@if($order->payer_type === 'company' && ($history['payer_cnpj'] ?? null))<dt class="col-sm-4">CNPJ no registro</dt><dd class="col-sm-8">{{ $history['payer_cnpj'] }}</dd>@endif</dl><p>Nomes e preços preservados no registro do pedido. A autorização do pedido não gera cobrança, reserva ou execução automaticamente.</p></div>
    <div class="table-responsive"><table class="table"><caption class="visually-hidden">Itens e preços registrados</caption><thead><tr><th scope="col">Exame</th><th scope="col">Quantidade</th><th scope="col">Preço unitário</th><th scope="col">Desconto</th></tr></thead><tbody>
    @foreach($order->items as $item)<tr><td class="text-break">{{ $item->description_snapshot }}</td><td>{{ $item->quantity }}</td><td>R$ {{ $item->unit_price }}</td><td>R$ {{ $item->discount_amount }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="card-body border-top">
        @error('receivable')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        @error('transition')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        @if(isset($history['authorization']))<p>Autorizado em {{ $history['authorization']['occurred_at'] }} por integrante #{{ $history['authorization']['actor_id'] }}.</p>@endif
        @if(isset($history['cancellation']))<p>Cancelado em {{ $history['cancellation']['occurred_at'] }} por integrante #{{ $history['cancellation']['actor_id'] }}.</p><p class="text-break">Motivo: {{ $history['cancellation']['reason'] }}</p>@endif
        @if($order->status === 'draft')
            @can('authorize', $order)
            <form method="post" action="{{ route('patients.orders.authorize', [$patient->public_id, $order->public_id]) }}" class="mb-6">
                @csrf<input type="hidden" name="expected_status" value="draft">
                <button type="submit" class="btn btn-primary">Autorizar pedido</button>
            </form>
            @endcan
        @endif
        @if(in_array($order->status, ['draft', 'authorized'], true))
            @can('cancel', $order)
            <form method="post" action="{{ route('patients.orders.cancel', [$patient->public_id, $order->public_id]) }}" class="mb-6">
                @csrf<input type="hidden" name="expected_status" value="{{ $order->status }}">
                <div class="row"><div class="col-md-8 mb-6"><label class="form-label" for="reason">Motivo do cancelamento</label><textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" maxlength="1000" rows="3" required aria-describedby="reason-help"></textarea><div class="form-text" id="reason-help">Informe o motivo operacional, sem dados clínicos desnecessários.</div>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                <button type="submit" class="btn btn-danger">Cancelar pedido</button>
            </form>
            @endcan
        @endif
        @can('viewAny', [\App\Domain\Orders\Models\ClinicalReceivable::class, $order])
        <a class="btn btn-label-primary mb-3" href="{{ route('patients.orders.receivables.index', [$patient->public_id, $order->public_id]) }}">Contas a receber</a>
        @endcan
        <a class="btn btn-label-secondary" href="{{ route('patients.orders.index', $patient->public_id) }}">Voltar aos pedidos</a></div>
</section>
@endsection
