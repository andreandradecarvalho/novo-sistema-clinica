@extends('layouts.app')
@section('title', 'Concessões de exames')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Concessões de exames</h1><p class="text-body-secondary">{{ $professional->user?->name }}</p></div>
@foreach(['authorization', 'reason', 'exam_type_id'] as $field) @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach
<section class="card mb-6"><h2 class="card-header h5">Histórico de concessões</h2><div class="table-responsive"><table class="table align-middle"><caption class="visually-hidden">Exames autorizados para este profissional</caption><thead><tr><th scope="col">Exame</th><th scope="col">Concessão</th><th scope="col">Situação / ações</th></tr></thead><tbody>
@forelse($grants as $grant)<tr><td>{{ $grant->exam->name }}</td><td>Em {{ $grant->granted_at->toIso8601String() }} por integrante #{{ $grant->granted_by }}.<p class="text-break">{{ $reasons[$grant->id]['grant'] }}</p></td><td>
@if($grant->revoked_at)<p>Revogada em {{ $grant->revoked_at->toIso8601String() }} por integrante #{{ $grant->revoked_by }}.</p><p class="text-break">{{ $reasons[$grant->id]['revocation'] }}</p>
@else<span class="badge bg-label-success">Vigente</span>
@can('manage', [\App\Domain\Executions\Models\ProfessionalExamAuthorization::class, $professional])
<form method="post" action="{{ route('professionals.authorizations.revoke', [$professional->id, $grant->id]) }}" class="mt-3">@csrf<label class="form-label" for="revoke-reason-{{ $grant->id }}">Motivo da revogação</label><textarea class="form-control mb-3" name="reason" id="revoke-reason-{{ $grant->id }}" maxlength="1000" rows="2" required></textarea><button class="btn btn-sm btn-danger" type="submit">Revogar concessão</button></form>
@endcan
@endif
</td></tr>@empty<tr><td colspan="3" class="text-center py-8">Nenhuma concessão registrada.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $grants->links('pagination::bootstrap-5') }}</div></section>
@can('manage', [\App\Domain\Executions\Models\ProfessionalExamAuthorization::class, $professional])
<section class="card"><h2 class="card-header h5">Conceder exame</h2><div class="card-body"><p>Conceda somente após validar a habilitação do profissional para o exame. A concessão não substitui a validação da clínica nem as demais permissões de execução.</p>
<form method="post" action="{{ route('professionals.authorizations.store', $professional->id) }}">@csrf<label class="form-label" for="exam_type_id">Exame desta página</label><select class="form-select mb-3" name="exam_type_id" id="exam_type_id" required><option value="">Selecione</option>@foreach($exams as $exam)<option value="{{ $exam->id }}">{{ $exam->code }} · {{ $exam->name }}</option>@endforeach</select>
<label class="form-label" for="grant-reason">Motivo operacional</label><textarea class="form-control mb-3" id="grant-reason" name="reason" rows="3" maxlength="1000" required></textarea><p class="form-text">Registre apenas a justificativa da autorização, sem dados de pacientes.</p><button class="btn btn-primary" type="submit">Conceder exame</button></form><div class="mt-3">{{ $exams->links('pagination::bootstrap-5') }}</div></div></section>
@endcan
<a class="btn btn-label-secondary mt-6" href="{{ route('professionals.show', $professional->id) }}">Voltar ao profissional</a>
@endsection
