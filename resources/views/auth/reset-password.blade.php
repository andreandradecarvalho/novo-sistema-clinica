@extends('layouts.auth')
@section('title', 'Redefinir senha')
@section('heading', 'Redefinir senha')
@section('description', 'Escolha uma senha forte para proteger sua conta.')
@section('illustration', Vite::asset('docs/layout/assets/img/illustrations/auth-reset-password-illustration-light.png'))
@section('content')
<form id="formAuthentication" class="mb-6" method="post" action="{{ route('password.update') }}">@csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <x-input name="email" label="E-mail" type="email" :value="$request->email" autocomplete="username" />
    <x-input name="password" label="Nova senha" type="password" autocomplete="new-password" />
    <x-input name="password_confirmation" label="Confirme a nova senha" type="password" autocomplete="new-password" />
    <p class="small text-body-secondary mb-6">Pelo menos 12 caracteres, com letras maiúsculas, minúsculas e números.</p>
    <button class="btn btn-primary d-grid w-100" type="submit">Salvar senha</button>
</form>
<div class="text-center"><a href="{{ route('login') }}"><x-icon name="arrow-left" class="me-1" />Voltar para entrar</a></div>
@endsection
