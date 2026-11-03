@extends('layouts.app')
@section('title', 'Verificar segundo fator')
@section('content')
<div class="card auth-card"><div class="card-body"><h1 class="h4 mb-4">Verificar segundo fator</h1>
<form method="post" action="{{ url('/two-factor-challenge') }}">@csrf
<x-input name="code" label="Código do aplicativo autenticador" autocomplete="one-time-code" />
<button class="btn btn-primary w-100" type="submit">Verificar código</button></form>
<details class="mt-4"><summary>Usar código de recuperação</summary>
<form method="post" action="{{ url('/two-factor-challenge') }}">@csrf
<x-input name="recovery_code" label="Código de recuperação" autocomplete="off" />
<button class="btn btn-outline-primary" type="submit">Recuperar acesso</button></form></details>
</div></div>
@endsection
