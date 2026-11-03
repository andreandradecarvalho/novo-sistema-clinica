@extends('layouts.app')
@section('title', 'Confirmar senha')
@section('content')
<div class="card auth-card"><div class="card-body"><h1 class="h4 mb-4">Confirmar senha</h1>
<p>Confirme sua senha para acessar as configurações de segurança.</p>
<form method="post" action="{{ route('password.confirm') }}">@csrf
<x-input name="password" label="Senha atual" type="password" autocomplete="current-password" />
<button class="btn btn-primary w-100" type="submit">Confirmar</button></form>
</div></div>
@endsection
