@extends('layouts.app')
@section('title', 'Redefinir senha')
@section('content')
<div class="card auth-card"><div class="card-body"><h1 class="h4 mb-4">Redefinir senha</h1>
<form method="post" action="{{ route('password.update') }}">@csrf
<input type="hidden" name="token" value="{{ $request->route('token') }}">
<x-input name="email" label="E-mail" type="email" :value="$request->email" autocomplete="username" />
<x-input name="password" label="Nova senha" type="password" autocomplete="new-password" />
<x-input name="password_confirmation" label="Confirme a nova senha" type="password" autocomplete="new-password" />
<button class="btn btn-primary w-100" type="submit">Salvar senha</button></form>
</div></div>
@endsection
