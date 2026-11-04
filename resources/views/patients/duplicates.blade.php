@extends('layouts.app')
@section('title', 'Revisar homônimos')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Revisar homônimos</h1><p class="text-body-secondary">{{ $patient->name }} · Cadastros com o mesmo nome normalizado nesta clínica.</p></div>
<p class="alert alert-info">Semelhança de nome não comprova que os registros são da mesma pessoa. Confira os dados de identificação e contato. Esta consulta não une nem altera cadastros.</p>
<section class="card"><h2 class="card-header h5">{{ $candidates->total() }} outros cadastros</h2><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<caption class="visually-hidden">Homônimos para comparação manual</caption><thead><tr><th scope="col">Nome</th><th scope="col">Nascimento</th><th scope="col">Identificação</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($candidates as $candidate)
<tr><td>{{ $candidate->name }}</td><td>{{ $candidate->birth_date?->format('d/m/Y') ?? 'Não informado' }}</td><td>{{ $candidate->is_partial ? 'Sem CPF' : 'CPF cadastrado' }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('patients.compare', [$patient->public_id, $candidate->public_id]) }}">Comparar</a></td></tr>
@empty
<tr><td colspan="4" class="text-center py-8 text-body-secondary">Nenhum outro cadastro com o mesmo nome normalizado.</td></tr>
@endforelse
</tbody></table></div><div class="card-body border-top">{{ $candidates->links('pagination::bootstrap-5') }}</div></section>
<a class="btn btn-label-secondary mt-6" href="{{ route('patients.show', $patient->public_id) }}">Voltar ao paciente</a>
@endsection
