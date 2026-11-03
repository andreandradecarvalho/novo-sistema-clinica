@extends('layouts.auth')
@section('title', 'Verificar segundo fator')
@section('heading', 'Verificar segundo fator')
@section('description', 'Digite o código do seu aplicativo autenticador para continuar.')
@section('illustration', Vite::asset('docs/layout/assets/img/illustrations/auth-two-step-illustration-light.png'))
@section('content')
<form id="formAuthentication" method="post" action="{{ url('/two-factor-challenge') }}">@csrf
    <x-input name="code" label="Código do aplicativo autenticador" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autofocus />
    <button class="btn btn-primary d-grid w-100" type="submit">Verificar código</button>
</form>
<details class="mt-6"><summary class="text-primary">Usar código de recuperação</summary>
    <form class="mt-4" method="post" action="{{ url('/two-factor-challenge') }}">@csrf
        <x-input name="recovery_code" label="Código de recuperação" autocomplete="off" />
        <button class="btn btn-label-primary w-100" type="submit">Recuperar acesso</button>
    </form>
</details>
@endsection
