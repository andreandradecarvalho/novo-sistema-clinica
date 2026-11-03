@extends('layouts.app')
@section('title', 'Vínculos empregatícios')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Vínculos empregatícios</h1><p class="text-body-secondary">{{ $patient->name }} · Histórico de empregadores, funções e vigências.</p></div>
<div class="d-flex justify-content-end mb-6">@can('create', [\App\Domain\Occupational\Models\EmploymentLink::class, $patient])<a class="btn btn-primary" href="{{ route('patients.employments.create', $patient->public_id) }}">Novo vínculo</a>@endcan</div>
@forelse($employments as $employment)
<section class="card mb-6"><div class="card-header"><h2 class="h5 mb-1">{{ $employment->context_encrypted['company_name'] ?? $employment->company?->name ?? 'Empregador indisponível' }}</h2><p class="text-body-secondary mb-0">{{ $employment->context_encrypted['company_unit_name'] ?? $employment->companyUnit?->name ?? 'Estabelecimento não informado' }}</p></div><div class="card-body">
<dl class="row"><dt class="col-sm-3">Vigência da versão</dt><dd class="col-sm-9">{{ $employment->starts_on?->format('d/m/Y') ?? 'Início não conhecido' }} até {{ $employment->ends_on?->format('d/m/Y') ?? 'Sem encerramento informado' }}</dd><dt class="col-sm-3">Matrícula</dt><dd class="col-sm-9">{{ $employment->registration_number ?? 'Não informada' }}</dd><dt class="col-sm-3">Função / setor</dt><dd class="col-sm-9">{{ $employment->job_title ?? 'Não informada' }} / {{ $employment->department ?? 'Não informado' }}</dd>@if($employment->termination_date)<dt class="col-sm-3">Desligamento</dt><dd class="col-sm-9">{{ $employment->termination_date->format('d/m/Y') }}</dd>@endif</dl>
@if($employment->ends_on === null && $employment->starts_on !== null) @can('update', [$employment, $patient])
@if($employment->company?->is_active && !$employment->company->trashed())<a class="btn btn-label-primary mb-4" href="{{ route('patients.employments.version', [$patient->public_id, $employment->id]) }}">Nova versão de função / estabelecimento</a>@endif
<details class="mt-3"><summary class="text-primary">Registrar desligamento</summary><form class="mt-4" method="post" action="{{ route('patients.employments.terminate', [$patient->public_id, $employment->id]) }}">@csrf
<label for="termination_date_{{ $employment->id }}" class="form-label">Data de desligamento</label><input type="date" id="termination_date_{{ $employment->id }}" name="termination_date" class="form-control mb-3" required><button type="submit" class="btn btn-label-danger">Registrar desligamento</button></form></details>
@endcan @endif
</div></section>
@empty<section class="card"><div class="card-body text-body-secondary text-center py-8">Nenhum vínculo registrado.</div></section>@endforelse
<div class="mt-6">{{ $employments->links('pagination::bootstrap-5') }}</div>
<a class="btn btn-label-secondary mt-6" href="{{ route('patients.show', $patient->public_id) }}">Voltar ao paciente</a>
@endsection
