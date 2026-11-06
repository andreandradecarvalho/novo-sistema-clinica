@extends('layouts.app')
@section('title', 'Contas a receber do pedido')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Contas a receber do pedido</h1><p class="text-body-secondary">{{ $patient->name }} — {{ $order->public_id }}</p></div>
@foreach(['financial_review', 'expected_fingerprint', 'expected_revision'] as $field) @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach
@if($review['required'])
<section class="card mb-6"><h2 class="card-header h5">Revisão financeira da repetição</h2><div class="card-body">
@if($review['approved'])<p class="alert alert-success">Preço histórico conferido: R$ {{ $review['amount'] }}, uma cobrança por item. Esta revisão não realiza cobrança nem pagamento.</p>
@else<p class="alert alert-warning">Nova cobrança e baixa permanecem bloqueadas até revisão explícita.</p>@endif
@if($review['eligible'] && ! $review['approved'])
@can('review', [\App\Domain\Orders\Models\ClinicalReceivable::class, $order])
<form method="post" action="{{ route('patients.orders.receivables.review', [$patient->public_id, $order->public_id]) }}">
@csrf<input type="hidden" name="expected_revision" value="{{ $review['revision'] }}"><input type="hidden" name="expected_fingerprint" value="{{ $review['fingerprint'] }}"><input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<p>Todos os itens estão concluídos. Confirme o preço histórico de R$ {{ $review['amount'] }}, contado uma vez por item. Tentativas anteriores serão preservadas.</p>
<label class="form-label" for="financial-review-reason">Justificativa da conferência</label><textarea class="form-control mb-3" name="reason" id="financial-review-reason" maxlength="1000" rows="2" required></textarea>
<p class="form-text">Não inclua informações clínicas ou de cartão. Esta operação não altera valores de contas, não estorna pagamentos e não encerra o atendimento.</p><button type="submit" class="btn btn-primary">Confirmar revisão financeira</button>
</form>
@endcan
@elseif(! $review['approved'])<p>A revisão exige todos os itens concluídos e, quando existir conta, o mesmo valor histórico. Valores divergentes ou conta cancelada exigem fluxo próprio de ajuste.</p>@endif
@foreach($review['history'] as $revision)<p>Revisão #{{ $revision['revision'] }} em {{ $revision['occurred_at'] }} por integrante #{{ $revision['actor_id'] }}: R$ {{ $revision['amount'] }}.</p><p class="text-break">{{ $revision['reason'] }}</p>@endforeach
</div></section>
@endif
<section class="card"><h2 class="card-header h5">Recebível clínico</h2><div class="card-body">
    <p>Pagador registrado: {{ $history['payer_name'] }}. O valor do pedido utiliza os preços preservados no pedido.</p>
    <p>Este registro interno não realiza pagamento nem cobrança externa. Baixa e cancelamento são registros internos auditados. Parcelas e estornos ainda não estão disponíveis.</p>
    @error('receivable')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
</div>
<div class="table-responsive"><table class="table"><caption class="visually-hidden">Recebíveis deste pedido</caption><thead><tr><th scope="col">Valor</th><th scope="col">Vencimento</th><th scope="col">Situação</th></tr></thead><tbody>
@forelse($order->receivables as $receivable)<tr><td>R$ {{ $receivable->amount }}</td><td>{{ $receivable->due_date->format('d/m/Y') }}</td><td>{{ ['open' => 'Em aberto', 'paid' => 'Baixado', 'canceled' => 'Cancelado'][$receivable->status] ?? $receivable->status }}</td></tr>
@empty<tr><td colspan="3">Nenhum recebível registrado.</td></tr>@endforelse
</tbody></table></div>
<div class="card-body border-top">
@foreach($order->receivables as $receivable)
    @php($event = $receivable->transitionSnapshot())
    @if($event !== null)
        <p>Registro financeiro #{{ $receivable->id }}: {{ $event['occurred_at'] }}, integrante #{{ $event['actor_id'] }}.</p>
        <p class="text-break">Motivo: {{ $event['reason'] }}</p>
    @else
        @foreach(['settle' => 'Registrar baixa interna', 'cancel' => 'Cancelar recebível'] as $operation => $label)
            @can($operation, $receivable)
            <form method="post" action="{{ route('patients.orders.receivables.'.$operation, [$patient->public_id, $order->public_id, $receivable->id]) }}" class="mb-6">
                @csrf<input type="hidden" name="expected_status" value="open"><input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <h3 class="h6">{{ $label }} — registro #{{ $receivable->id }}</h3>
                <label class="form-label" for="reason-{{ $receivable->id }}-{{ $operation }}">Motivo operacional</label>
                <textarea class="form-control @error('reason') is-invalid @enderror" id="reason-{{ $receivable->id }}-{{ $operation }}" name="reason" maxlength="1000" rows="2" required></textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <p class="form-text">Não inclua dados de cartão ou informações clínicas. A baixa não executa pagamento. Recebível baixado não pode ser cancelado neste fluxo.</p>
                <button type="submit" class="btn {{ $operation === 'settle' ? 'btn-primary' : 'btn-danger' }}">{{ $label }}</button>
            </form>
            @endcan
        @endforeach
    @endif
@endforeach
@error('idempotency_key')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@error('expected_status')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@if(in_array($order->status, ['authorized', 'in_progress', 'completed'], true) && $order->receivables->isEmpty())
    @can('create', [\App\Domain\Orders\Models\ClinicalReceivable::class, $order])
    <form method="post" action="{{ route('patients.orders.receivables.store', [$patient->public_id, $order->public_id]) }}" class="mb-6">
        @csrf
        <div class="row"><div class="col-md-6 mb-6"><label class="form-label" for="due_date">Vencimento</label><input type="date" class="form-control @error('due_date') is-invalid @enderror" id="due_date" name="due_date" value="{{ old('due_date') }}" min="1000-01-01" max="9999-12-31" required>@error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
        <button type="submit" class="btn btn-primary">Registrar recebível do pedido</button>
    </form>
    @endcan
@endif
<a class="btn btn-label-secondary" href="{{ route('patients.orders.show', [$patient->public_id, $order->public_id]) }}">Voltar ao pedido</a>
</div></section>
@endsection
