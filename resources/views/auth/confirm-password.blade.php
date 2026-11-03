@extends('layouts.auth')
@section('title', 'Confirmar senha')
@section('heading', 'Confirmar senha')
@section('description', 'Confirme sua senha para acessar as configurações de segurança.')
@section('illustration', Vite::asset('docs/layout/assets/img/illustrations/auth-reset-password-illustration-light.png'))
@section('content')
<form id="formAuthentication" method="post" action="{{ route('password.confirm') }}">@csrf
    <x-input name="password" label="Senha atual" type="password" autocomplete="current-password" autofocus />
    <button class="btn btn-primary d-grid w-100" type="submit">Confirmar</button>
</form>
@endsection
