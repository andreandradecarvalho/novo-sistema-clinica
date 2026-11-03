@extends('layouts.app')
@section('title', 'Recuperar acesso')
@section('content')
<div class="card auth-card"><div class="card-body"><h1 class="h4 mb-4">Recuperar acesso</h1>
<form method="post" action="{{ route('password.email') }}">@csrf
<x-input name="email" label="E-mail" type="email" autocomplete="username" />
<button class="btn btn-primary w-100" type="submit">Enviar link de recuperação</button></form>
</div></div>
@endsection
