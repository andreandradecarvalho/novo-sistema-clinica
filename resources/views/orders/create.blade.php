@extends('layouts.app')
@section('title', 'Novo pedido de exames')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Novo pedido de exames</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card"><h2 class="card-header h5">Exames do pedido particular</h2><div class="card-body">
    <p id="order-help">Selecione de 1 a 20 exames desta página. Cada exame terá uma unidade, sem desconto, pelo preço do catálogo no registro. O rascunho ainda não autoriza atendimento nem gera cobrança.</p>
    <form method="post" action="{{ route('patients.orders.store', $patient->public_id) }}">
        @csrf
        <fieldset aria-describedby="order-help"><legend class="h6">Exames ativos com preço definido</legend>
        @forelse($exams as $exam)
            <div class="form-check mb-3"><input class="form-check-input" id="exam-{{ $exam->id }}" name="exam_ids[]" type="checkbox" value="{{ $exam->id }}"><label class="form-check-label" for="exam-{{ $exam->id }}">{{ $exam->code }} — {{ $exam->name }} — R$ {{ $exam->price }}</label></div>
        @empty
            <p>Nenhum exame disponível nesta página. Confira o catálogo e os preços.</p>
        @endforelse
        </fieldset>
        @error('exam_ids')<div class="text-danger mb-3" role="alert">{{ $message }}</div>@enderror
        @foreach($errors->get('exam_ids.*') as $messages) @foreach($messages as $message)<div class="text-danger mb-3" role="alert">{{ $message }}</div>@endforeach @endforeach
        <div class="d-flex gap-3 mt-6"><button type="submit" class="btn btn-primary" @disabled($exams->isEmpty())>Registrar rascunho</button><a class="btn btn-label-secondary" href="{{ route('patients.orders.index', $patient->public_id) }}">Cancelar</a></div>
    </form>
    <div class="mt-6">{{ $exams->links('pagination::bootstrap-5') }}<p class="form-text">Ao mudar de página, a seleção atual é descartada.</p></div>
</div></section>
@endsection
