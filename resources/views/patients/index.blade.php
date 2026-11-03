@extends('layouts.app')
@section('title', 'Pacientes')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Pacientes</h1><p class="text-body-secondary">Cadastros de identificação desta clínica.</p></div>
<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">{{ $patients->total() }} cadastros</h2>@can('create', \App\Domain\Patients\Models\Patient::class)<a href="{{ route('patients.create') }}" class="btn btn-primary">Novo paciente</a>@endcan</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Pacientes desta clínica</caption>
        <thead><tr><th scope="col">Nome</th><th scope="col">Nascimento</th><th scope="col">Identificação</th><th scope="col">Ações</th></tr></thead>
        <tbody>@forelse($patients as $patient)<tr><td><a href="{{ route('patients.show', $patient->public_id) }}">{{ $patient->name }}</a></td><td>{{ $patient->birth_date?->format('d/m/Y') ?? 'Não informado' }}</td><td><span class="badge bg-label-secondary">{{ $patient->is_partial ? 'Sem CPF' : 'CPF cadastrado' }}</span></td><td><a class="btn btn-sm btn-label-primary" href="{{ route('patients.show', $patient->public_id) }}">Consultar</a></td></tr>@empty<tr><td colspan="4" class="text-center py-8 text-body-secondary">Nenhum paciente cadastrado.</td></tr>@endforelse</tbody>
    </table></div><div class="card-body border-top">{{ $patients->links('pagination::bootstrap-5') }}</div>
</section>
@endsection
