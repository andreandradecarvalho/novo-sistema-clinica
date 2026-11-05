@extends('layouts.app')
@section('title', 'Versão do protocolo')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">{{ $protocol->code }} · Versão {{ $protocol->version }}</h1><p class="text-body-secondary">{{ $protocol->approved_at ? 'Aprovação registrada em '.$protocol->approved_at->format('d/m/Y H:i:s').' UTC' : 'Aguardando aprovação desta versão' }}</p></div>
@if($protocol->approved_at)<p class="text-body-secondary">Aprovado por {{ $protocol->approver?->user?->name ?? 'profissional registrado' }}.</p>@endif
<section class="card mb-6"><h2 class="card-header h5">Definição preservada</h2><div class="card-body">
    <dl class="row"><dt class="col-sm-4">Fonte</dt><dd class="col-sm-8 text-break">{{ $rules['source_reference'] }}</dd><dt class="col-sm-4">Função</dt><dd class="col-sm-8">{{ $rules['job_title'] ?? 'Não informada' }}</dd><dt class="col-sm-4">Vigência</dt><dd class="col-sm-8">{{ $protocol->valid_from?->format('d/m/Y') ?? 'Não informada' }} até {{ $protocol->valid_until?->format('d/m/Y') ?? 'Não informada' }}</dd></dl>
    <h3 class="h6">Contexto informado</h3><p class="text-break" style="white-space: pre-wrap">{{ $rules['risk_context'] }}</p>
    <h3 class="h6">Exames desta versão</h3><ul>@foreach($rules['exams'] as $exam)<li>{{ $exam['code'] }} — {{ $exam['name'] }}</li>@endforeach</ul>
    <div class="d-flex gap-3 flex-wrap">@can('create', [\App\Domain\Occupational\Models\OccupationalProtocol::class, $program])<a class="btn btn-label-primary" href="{{ route('programs.protocols.version', [$program->id, $protocol->id]) }}">Cadastrar nova versão</a>@endcan<a class="btn btn-label-secondary" href="{{ route('programs.protocols.index', $program->id) }}">Voltar às versões</a></div>
</div></section>
@if(!$protocol->approved_at)
@can('approve', $protocol)
<section class="card"><h2 class="card-header h5">Aprovar esta versão</h2><div class="card-body">
<p>A aprovação será vinculada ao seu cadastro profissional e a esta versão. Alterações futuras exigem nova versão e nova aprovação. Pode ser solicitada confirmação de senha.</p>
<form method="post" action="{{ route('programs.protocols.approve', [$program->id, $protocol->id]) }}">@csrf
<div class="form-check mb-6"><input type="checkbox" name="attested" id="attested" value="1" required class="form-check-input @error('attested') is-invalid @enderror" @error('attested') aria-invalid="true" aria-describedby="attested-error" @enderror><label for="attested" class="form-check-label">Revisei a fonte, o contexto, a vigência e os exames desta versão e confirmo sua aprovação.</label>@error('attested')<div id="attested-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
@error('approval')<div class="alert alert-danger">{{ $message }}</div>@enderror
<button type="submit" class="btn btn-primary">Confirmar aprovação</button>
</form></div></section>
@endcan
@endif
@endsection
