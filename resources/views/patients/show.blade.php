@extends('layouts.app')
@section('title', 'Cadastro do paciente')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Cadastro do paciente</h1><p class="text-body-secondary">Identificação e contato nesta clínica.</p></div>
<section class="card"><div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3"><h2 class="h5 mb-0">{{ $patient->name }}</h2>@can('update', $patient)<a class="btn btn-primary" href="{{ route('patients.edit', $patient->public_id) }}">Editar cadastro</a>@endcan</div><div class="card-body">
<dl class="row"><dt class="col-sm-4">Nascimento</dt><dd class="col-sm-8">{{ $patient->birth_date?->format('d/m/Y') ?? 'Não informado' }}</dd><dt class="col-sm-4">CPF</dt><dd class="col-sm-8">{{ $patient->is_partial ? 'Não informado' : 'Cadastrado com proteção de dados' }}</dd><dt class="col-sm-4">E-mail</dt><dd class="col-sm-8">{{ $patient->email ?? 'Não informado' }}</dd><dt class="col-sm-4">Celular</dt><dd class="col-sm-8">{{ $patient->mobile_phone ?? 'Não informado' }}</dd></dl>
@can('viewAny', [\App\Domain\Occupational\Models\EmploymentLink::class, $patient])<a class="btn btn-primary me-3" href="{{ route('patients.employments.index', $patient->public_id) }}">Vínculos empregatícios</a>@endcan
<a href="{{ route('patients.index') }}" class="btn btn-label-secondary">Voltar aos pacientes</a>
</div></section>
@endsection
