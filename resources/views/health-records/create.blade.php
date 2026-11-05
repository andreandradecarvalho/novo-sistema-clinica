@extends('layouts.app')
@section('title', 'Coletar resposta clínica')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Coletar resposta clínica</h1><p class="text-body-secondary">{{ $patient->name }} · Atendimento {{ $visit->public_id }}</p></div>
@foreach(['question_id', 'answer', 'idempotency_key', 'health_record'] as $field)@error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror @endforeach
@if($snapshot !== null)
<section class="card mb-6"><h2 class="card-header h5">{{ $snapshot->code }} · versão {{ $snapshot->version }}</h2><div class="card-body"><form method="post" action="{{ route('patients.visits.health-records.store', [$patient->public_id, $visit->public_id]) }}">
@csrf<input type="hidden" name="question_id" value="{{ $snapshot->questionId }}"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
<div class="row"><div class="col-12 mb-6"><label class="form-label" for="answer">{{ $snapshot->label }}</label><textarea class="form-control" id="answer" name="answer" rows="5" maxlength="10000" required autocomplete="off"></textarea></div></div>
<p class="form-text">Confira paciente e versão antes de registrar. O envio preserva este conteúdo e não substitui respostas anteriores.</p><button class="btn btn-primary" type="submit">Registrar resposta</button>
</form></div></section>
@endif
<section class="card"><h2 class="card-header h5">Escolher a versão da pergunta</h2><div class="table-responsive"><table class="table"><caption class="visually-hidden">Versões de perguntas disponíveis para seleção explícita</caption><thead><tr><th scope="col">Código / versão</th><th scope="col">Pergunta</th><th scope="col">Ação</th></tr></thead><tbody>
@forelse($questions as $question)<tr><td>{{ $question->code }} · {{ $question->version }}</td><td>{{ $question->label }}</td><td><a href="{{ route('patients.visits.health-records.create', [$patient->public_id, $visit->public_id, 'question_id' => $question->id]) }}">Selecionar versão {{ $question->version }}</a></td></tr>
@empty<tr><td colspan="3" class="text-center py-8">Nenhuma pergunta de texto disponível.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $questions->links('pagination::bootstrap-5') }}<a class="btn btn-label-secondary" href="{{ route('patients.visits.health-records.index', [$patient->public_id, $visit->public_id]) }}">Voltar aos registros</a></div></section>
@endsection
