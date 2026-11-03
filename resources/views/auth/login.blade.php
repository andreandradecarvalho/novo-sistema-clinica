@extends('layouts.auth')
@section('title', 'Bem-vindo à Precordis')
@section('heading', 'Bem-vindo à Precordis')
@section('description', 'Entre na sua conta para acessar a clínica.')
@section('illustration', Vite::asset('docs/layout/assets/img/illustrations/auth-login-illustration-light.png'))
@section('content')
<form id="formAuthentication" class="mb-6" method="post" action="{{ route('login') }}">@csrf
    <x-input name="email" label="E-mail" type="email" autocomplete="username" autofocus />
    <x-input name="password" label="Senha" type="password" autocomplete="current-password" />
    <div class="d-flex justify-content-end mb-6"><a href="{{ route('password.request') }}">Esqueci minha senha</a></div>
    <button class="btn btn-primary d-grid w-100" type="submit">Entrar</button>
</form>
<p class="mb-0 text-center text-body-secondary small"><x-icon name="lock" class="me-1" />Acesso exclusivo para integrantes da clínica.</p>
@endsection
