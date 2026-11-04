@extends('layouts.app')
@section('title', 'Consentimentos e termos')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Consentimentos e termos</h1><p class="text-body-secondary">{{ $patient->name }} · Histórico de manifestações por finalidade</p></div>
<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">Manifestações registradas</h2>
        @can('create', [\App\Domain\Patients\Models\PatientConsent::class, $patient])<a class="btn btn-primary" href="{{ route('patients.consents.create', $patient->public_id) }}">Registrar manifestação</a>@endcan
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <caption class="visually-hidden">Histórico de manifestações deste paciente</caption>
        <thead><tr><th scope="col">Data (UTC)</th><th scope="col">Finalidade</th><th scope="col">Manifestação</th><th scope="col">Versão do termo</th><th scope="col">Ações</th></tr></thead>
        <tbody>@forelse($consents as $consent)
            <tr><td>{{ $consent->occurred_at->format('d/m/Y H:i:s') }}</td><td class="text-break">{{ $consent->purpose }}</td><td>{{ $consent->decision->label() }}</td><td>{{ $consent->policy_version }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('patients.consents.show', [$patient->public_id, $consent->id]) }}">Consultar evidência</a></td></tr>
        @empty<tr><td colspan="5" class="text-center text-body-secondary py-8">Nenhuma manifestação registrada.</td></tr>@endforelse</tbody>
    </table></div>
    <div class="card-body border-top">{{ $consents->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('patients.show', $patient->public_id) }}">Voltar ao paciente</a></div>
</section>
@endsection
