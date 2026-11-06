@extends('layouts.app')
@section('title', 'Política financeira para recusa e incompletude')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Política financeira para recusa e incompletude</h1><p class="text-body-secondary">Configuração desta clínica.</p></div>
<section class="card"><h2 class="card-header h5">Política para desfechos excepcionais</h2><div class="card-body">
<div class="alert alert-info" role="status">Os desfechos usam a versão da política vigente no registro. Alterar esta configuração não modifica desfechos anteriores ou cobranças existentes.</div>
<p>Política atual: <strong>{{ $snapshot['mode'] === null ? 'Não configurada' : \App\Domain\Orders\Enums\ExceptionFinancialMode::from($snapshot['mode'])->label() }}</strong>. Versão {{ $snapshot['revision'] }}.</p>
@foreach(['financial_policy', 'mode', 'expected_revision', 'idempotency_key'] as $field) @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach
@can('manageExceptionPolicy', \App\Domain\Tenancy\Models\Setting::class)
<form method="post" action="{{ route('settings.exception-financial-policy.update') }}">
@csrf<input type="hidden" name="expected_revision" value="{{ $snapshot['revision'] }}"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
<div class="row"><div class="col-md-8 mb-6"><label class="form-label" for="mode">Política da clínica</label><select class="form-select" id="mode" name="mode" required><option value="">Selecione explicitamente</option>@foreach($modes as $mode)<option value="{{ $mode->value }}" @selected($snapshot['mode'] === $mode->value)>{{ $mode->label() }}</option>@endforeach</select></div></div>
<button type="submit" class="btn btn-primary">Registrar política</button>
</form>
@endcan
@if($snapshot['history'] !== [])<h3 class="h6 mt-6">Histórico de configurações</h3><div class="table-responsive"><table class="table"><thead><tr><th>Versão</th><th>Política</th><th>Integrante</th><th>Data UTC</th></tr></thead><tbody>@foreach(array_reverse($snapshot['history']) as $event)<tr><td>{{ $event['revision'] }}</td><td>{{ \App\Domain\Orders\Enums\ExceptionFinancialMode::from($event['mode'])->label() }}</td><td>#{{ $event['actor_id'] }}</td><td>{{ $event['occurred_at'] }}</td></tr>@endforeach</tbody></table></div>@endif
</div></section>
@endsection
