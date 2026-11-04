@extends('layouts.app')
@section('title', 'Buscar pacientes')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Buscar pacientes</h1><p class="text-body-secondary">Busque por CPF exato ou pelo início do nome. Confira a identificação antes de abrir ou alterar um cadastro.</p></div>
<div class="row g-6">
    <div class="col-lg-6"><section class="card h-100"><h2 class="card-header h5">CPF exato</h2><div class="card-body">
        <form method="post" action="{{ route('patients.search.store') }}">@csrf<input type="hidden" name="mode" value="cpf">
            <div class="mb-6"><label for="cpf" class="form-label">CPF</label><input id="cpf" name="cpf" class="form-control" inputmode="numeric" autocomplete="off" maxlength="14" required></div>
            <button class="btn btn-primary" type="submit">Buscar por CPF</button>
        </form>
    </div></section></div>
    <div class="col-lg-6"><section class="card h-100"><h2 class="card-header h5">Nome e nascimento</h2><div class="card-body">
        <form method="post" action="{{ route('patients.search.store') }}">@csrf<input type="hidden" name="mode" value="name">
            <div class="mb-6"><label for="name" class="form-label">Início do nome</label><input id="name" name="name" class="form-control" autocomplete="off" minlength="3" maxlength="180" required><div class="form-text">Informe pelo menos três caracteres.</div></div>
            <div class="mb-6"><label for="birth_date" class="form-label">Nascimento (opcional)</label><input id="birth_date" name="birth_date" type="date" class="form-control"></div>
            <button class="btn btn-primary" type="submit">Buscar por nome</button>
        </form>
    </div></section></div>
</div>
<a class="btn btn-label-secondary mt-6" href="{{ route('patients.index') }}">Voltar aos pacientes</a>
@endsection
