@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="card auth-card"><div class="card-body"><h1 class="h4 mb-4">Entrar</h1>
<form method="post" action="{{ route('login') }}">@csrf
<x-input name="email" label="E-mail" type="email" autocomplete="username" />
<x-input name="password" label="Senha" type="password" autocomplete="current-password" />
<button class="btn btn-primary w-100" type="submit">Entrar</button>
</form><a class="d-block mt-4" href="{{ route('password.request') }}">Esqueci minha senha</a>
</div></div>
@endsection
