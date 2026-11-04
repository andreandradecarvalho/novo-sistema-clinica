@extends('layouts.app')
@section('title', 'Histórico de merges')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Histórico de merges</h1><p class="text-body-secondary">{{ $patient->name }} · Cadastro sobrevivente.</p></div>
@forelse($merges as $merge)
<section class="card mb-6"><h2 class="card-header h5">Merge #{{ $merge->id }}</h2><div class="card-body">
<dl class="row"><dt class="col-sm-3">Registrado em</dt><dd class="col-sm-9">{{ $merge->created_at->setTimezone(app(\App\Domain\Tenancy\Services\TenantContext::class)->require()->timezone)->format('d/m/Y H:i') }}</dd><dt class="col-sm-3">Operador</dt><dd class="col-sm-9">Integrante #{{ $merge->merged_by }}</dd><dt class="col-sm-3">Origem arquivada</dt><dd class="col-sm-9">{{ $merge->mergedPatient?->name ?? 'Cadastro indisponível' }}</dd><dt class="col-sm-3">Nascimento da origem</dt><dd class="col-sm-9">{{ $merge->mergedPatient?->birth_date?->format('d/m/Y') ?? 'Não informado' }}</dd><dt class="col-sm-3">Contato da origem</dt><dd class="col-sm-9 text-break">{{ $merge->mergedPatient?->email ?? 'E-mail não informado' }} · {{ $merge->mergedPatient?->mobile_phone ?? 'Celular não informado' }}</dd><dt class="col-sm-3">Motivo da decisão</dt><dd class="col-sm-9 text-break">{{ $merge->reason }}</dd></dl>
<p class="text-body-secondary mb-0">Registro preservado. O merge não copiou campos nem transferiu a propriedade dos vínculos da origem. A consulta de vínculos reúne as origens preservadas.</p>
</div></section>
@empty
<section class="card"><div class="card-body text-center py-8 text-body-secondary">Nenhum merge registrado para este cadastro.</div></section>
@endforelse
<div class="my-6">{{ $merges->links('pagination::bootstrap-5') }}</div>
<a class="btn btn-label-secondary" href="{{ route('patients.show', $patient->public_id) }}">Voltar ao paciente</a>
@endsection
