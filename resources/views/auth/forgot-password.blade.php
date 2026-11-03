@extends('layouts.auth')
@section('title', 'Esqueceu sua senha?')
@section('heading', 'Esqueceu sua senha?')
@section('description', 'Informe seu e-mail para receber um link de recuperação.')
@section('illustration', Vite::asset('docs/layout/assets/img/illustrations/auth-forgot-password-illustration-light.png'))
@section('content')
<form id="formAuthentication" class="mb-6" method="post" action="{{ route('password.email') }}">@csrf
    <x-input name="email" label="E-mail" type="email" autocomplete="username" autofocus />
    <button class="btn btn-primary d-grid w-100" type="submit">Enviar link de recuperação</button>
</form>
<div class="text-center"><a href="{{ route('login') }}" class="d-inline-flex align-items-center gap-1"><x-icon name="arrow-left" />Voltar para entrar</a></div>
@endsection
