@extends('layouts.app')
@section('title', 'Comparar cadastros')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Comparar cadastros</h1><p class="text-body-secondary">Confira a identidade por fontes autorizadas. Nome e contato semelhantes podem pertencer a pessoas diferentes.</p></div>
<div class="row g-6">
@foreach([$patient, $candidate] as $subject)
<div class="col-lg-6"><section class="card h-100"><h2 class="card-header h5">{{ $loop->first ? 'Cadastro de origem' : 'Cadastro comparado' }}</h2><div class="card-body">
<dl class="row"><dt class="col-sm-4">Nome</dt><dd class="col-sm-8">{{ $subject->name }}</dd><dt class="col-sm-4">Nascimento</dt><dd class="col-sm-8">{{ $subject->birth_date?->format('d/m/Y') ?? 'Não informado' }}</dd><dt class="col-sm-4">CPF</dt><dd class="col-sm-8">{{ $subject->is_partial ? 'Não informado' : 'Cadastrado com proteção de dados' }}</dd><dt class="col-sm-4">E-mail</dt><dd class="col-sm-8 text-break">{{ $subject->email ?? 'Não informado' }}</dd><dt class="col-sm-4">Celular</dt><dd class="col-sm-8">{{ $subject->mobile_phone ?? 'Não informado' }}</dd></dl>
@if(isset($employmentCounts[$subject->id]))<p>{{ $employmentCounts[$subject->id] }} versões de vínculos empregatícios neste cadastro/grupo.</p><a class="btn btn-label-secondary mb-4" href="{{ route('patients.employments.index', $subject->public_id) }}">Conferir vínculos</a>@endif
<a class="btn btn-label-primary" href="{{ route('patients.show', $subject->public_id) }}">Consultar cadastro</a>
</div></section></div>
@endforeach
</div>
<p class="text-body-secondary mt-6">Esta comparação não registra uma decisão de identidade nem realiza merge. Para registrar uma decisão, use o formulário autorizado abaixo.</p>
@can('merge', [$patient, $candidate])
<section class="card my-6"><h2 class="card-header h5">Confirmar identidade e registrar merge cadastral</h2><div class="card-body">
<p>Escolha o cadastro sobrevivente. Seus campos serão mantidos; os dados da origem serão preservados no histórico, sem cópia automática de campos. Os vínculos dos dois cadastros serão reunidos na consulta do sobrevivente. A origem será arquivada e deixará de aparecer nas buscas. Não há desfazer automático.</p>
<p class="alert alert-warning">A origem deve estar sem CPF, conta, associação cadastral de empresa ou origem legada. Vínculos empregatícios serão consultados pelo sobrevivente, com a identificação original preservada. Outras dependências e grupos já consolidados como origem não são suportados. Nascimentos conhecidos divergentes impedem o merge. Se um cadastro tiver CPF, mantenha-o como sobrevivente. A confirmação exige senha recente.</p>
<form method="post" action="{{ route('patients.merge', [$patient->public_id, $candidate->public_id]) }}">@csrf
<input type="hidden" name="review_token" value="{{ $reviewToken }}">
<div class="mb-6"><label for="survivor_id" class="form-label">Cadastro sobrevivente</label><select id="survivor_id" name="survivor_id" class="form-select" required><option value="">Selecione após conferir os dados</option><option value="{{ $patient->id }}">Cadastro de origem · {{ $patient->name }}</option><option value="{{ $candidate->id }}">Cadastro comparado · {{ $candidate->name }}</option></select></div>
<div class="mb-6"><label for="reason" class="form-label">Motivo e evidência da decisão</label><textarea id="reason" name="reason" class="form-control" minlength="10" maxlength="1000" rows="3" required></textarea><div class="form-text">Registre a conferência realizada, sem reproduzir CPF, documentos ou conteúdo clínico.</div></div>
<div class="form-check mb-6"><input class="form-check-input" type="checkbox" id="identity_confirmed" name="identity_confirmed" value="1" required><label class="form-check-label" for="identity_confirmed">Conferi a identidade por fonte autorizada e confirmo que os cadastros são da mesma pessoa.</label></div>
<button class="btn btn-danger" type="submit">Confirmar merge e arquivar origem</button>
</form></div></section>
@endcan
<a class="btn btn-label-secondary" href="{{ route('patients.duplicates', $patient->public_id) }}">Voltar à revisão</a>
@endsection
