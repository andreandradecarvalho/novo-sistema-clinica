@extends('layouts.app')
@section('title', 'Referência de programa ocupacional')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Referência de programa ocupacional</h1><p class="text-body-secondary">Versão {{ $program->version }}</p></div>
<section class="card"><h2 class="card-header h5">{{ $context['company_name'] }}</h2><div class="card-body">
<dl class="row"><dt class="col-sm-4">Responsável informado</dt><dd class="col-sm-8">{{ $context['professional_name'] }}</dd><dt class="col-sm-4">Conselho registrado</dt><dd class="col-sm-8">{{ $context['council'] ?? 'Não informado' }} {{ $context['council_uf'] }} {{ $context['council_number'] }}</dd><dt class="col-sm-4">Vigência informada</dt><dd class="col-sm-8">{{ $program->valid_from?->format('d/m/Y') ?? 'Não informado' }} até {{ $program->valid_until?->format('d/m/Y') ?? 'Não informado' }}</dd><dt class="col-sm-4">Origem</dt><dd class="col-sm-8 text-break">{{ $program->source_reference }}</dd></dl>
<h3 class="h6">Contexto preservado</h3><p class="text-break" style="white-space: pre-wrap">{{ $context['context'] }}</p>
@can('viewAny', [\App\Domain\Occupational\Models\OccupationalProtocol::class, $program])<a class="btn btn-label-primary mb-6" href="{{ route('programs.protocols.index', $program->id) }}">Protocolos deste programa</a>@endcan
<p class="text-body-secondary">Este registro não indica aprovação clínica nem determina quais exames realizar.</p><a class="btn btn-label-secondary" href="{{ route('companies.programs.index', $company->public_id) }}">Voltar às versões</a>
</div></section>
@endsection
