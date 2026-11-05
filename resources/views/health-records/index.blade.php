@extends('layouts.app')
@section('title', 'Respostas clínicas')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Respostas clínicas</h1><p class="text-body-secondary">{{ $patient->name }} · Atendimento {{ $visit->public_id }}</p></div>
<section class="card"><h2 class="card-header h5">{{ $records->total() }} registros de coleta</h2><div class="card-body">
@if(in_array($visit->status, ['open', 'in_progress'], true))
@can('create', [\App\Domain\Clinical\Models\HealthRecord::class, $visit])<a class="btn btn-primary" href="{{ route('patients.visits.health-records.create', [$patient->public_id, $visit->public_id]) }}">Coletar resposta</a>
@endcan
@endif
<p class="mt-3">Cada envio preserva uma resposta e a versão da pergunta escolhida. Registros anteriores permanecem no histórico.</p></div>
<div class="table-responsive"><table class="table"><caption class="visually-hidden">Registros clínicos deste atendimento</caption><thead><tr><th scope="col">Data da coleta (UTC)</th><th scope="col">Responsável</th><th scope="col">Registro</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->filled_at?->toIso8601String() }}</td><td>Integrante #{{ $record->filled_by }}</td><td><a href="{{ route('patients.visits.health-records.show', [$patient->public_id, $visit->public_id, $record->public_id]) }}">Consultar resposta</a></td></tr>
@empty<tr><td colspan="3" class="text-center py-8">Nenhuma resposta coletada.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $records->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('patients.visits.show', [$patient->public_id, $visit->public_id]) }}">Voltar ao atendimento</a></div></section>
@endsection
