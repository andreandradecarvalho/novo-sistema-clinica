@extends('layouts.app')
@section('title', 'Nova concessão empresarial')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Nova concessão empresarial</h1><p class="text-body-secondary">{{ $company->name }} · Horários no fuso {{ app(\App\Domain\Tenancy\Services\TenantContext::class)->require()->timezone }}.</p></div>
<section class="card"><h2 class="card-header h5">Integrante, categoria e vigência</h2><div class="card-body">
<p>Escolha o integrante nesta página. A concessão exige também permissão específica da categoria; não concede papéis nem acesso a anamnese ou resultados completos. O portal empresarial será disponibilizado em etapa própria.</p>
<form method="post" action="{{ route('companies.accesses.store', $company->public_id) }}">@csrf
<div class="mb-6"><label for="user_id" class="form-label">Integrante ativo</label><select id="user_id" name="user_id" class="form-select" required><option value="">Selecione</option>@foreach($users as $member)<option value="{{ $member->id }}">{{ $member->name }} · {{ $member->email }}</option>@endforeach</select></div>
<div class="mb-6"><label for="scope" class="form-label">Categoria</label><select id="scope" name="scope" class="form-select" required>@foreach($scopes as $scope)<option value="{{ $scope->value }}">{{ $scope->label() }}</option>@endforeach</select></div>
<div class="row"><div class="col-md-6 mb-6"><label for="valid_from" class="form-label">Início</label><input id="valid_from" name="valid_from" type="datetime-local" class="form-control" required></div><div class="col-md-6 mb-6"><label for="valid_until" class="form-label">Fim (opcional)</label><input id="valid_until" name="valid_until" type="datetime-local" class="form-control"></div></div>
<div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Registrar concessão</button><a class="btn btn-label-secondary" href="{{ route('companies.accesses.index', $company->public_id) }}">Cancelar</a></div>
</form><div class="mt-6">{{ $users->links('pagination::bootstrap-5') }}</div>
</div></section>
@endsection
