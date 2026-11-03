@extends('layouts.app')
@section('title', 'Segurança da conta')
@section('content')
<h1 class="h3 mb-4">Segurança da conta</h1>
<div class="security-grid">
<section class="card"><div class="card-body"><h2 class="h5">Alterar senha</h2>
<p>Use pelo menos 12 caracteres, letras maiúsculas, minúsculas e números.</p>
@if(auth()->user()->must_change_password)<p class="alert alert-warning">Altere a senha inicial antes de acessar a clínica.</p>@endif
<form method="post" action="{{ url('/user/password') }}">@csrf @method('PUT')
<x-input name="current_password" label="Senha atual" type="password" autocomplete="current-password" />
<x-input name="password" label="Nova senha" type="password" autocomplete="new-password" />
<x-input name="password_confirmation" label="Confirme a nova senha" type="password" autocomplete="new-password" />
<button class="btn btn-primary" type="submit">Alterar senha</button></form>
@if($errors->updatePassword->any())<div role="alert">@foreach($errors->updatePassword->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
</div></section>
<section class="card"><div class="card-body"><h2 class="h5">Autenticação em dois fatores</h2>
@if(!auth()->user()->two_factor_secret)
<p>Ative o segundo fator para proteger sua conta. Ele é obrigatório para acesso clínico.</p>
<form method="post" action="{{ url('/user/two-factor-authentication') }}">@csrf<button class="btn btn-primary" type="submit">Ativar segundo fator</button></form>
@else
@if(!auth()->user()->two_factor_confirmed_at)
<p>Leia o QR code com seu aplicativo autenticador e confirme o código.</p>
<div class="qr-code">{!! auth()->user()->twoFactorQrCodeSvg() !!}</div>
<form method="post" action="{{ url('/user/confirmed-two-factor-authentication') }}">@csrf
<x-input name="code" label="Código de verificação" autocomplete="one-time-code" />
<button class="btn btn-primary" type="submit">Confirmar ativação</button></form>
@else
<p class="alert alert-success">Segundo fator confirmado.</p>
<details><summary>Códigos de recuperação — guarde em local seguro</summary><ul class="recovery-codes">@foreach(auth()->user()->recoveryCodes() as $code)<li><code>{{ $code }}</code></li>@endforeach</ul></details>
<form class="mt-4" method="post" action="{{ url('/user/two-factor-recovery-codes') }}">@csrf<button class="btn btn-outline-primary" type="submit">Gerar novos códigos</button></form>
@endif
<form class="mt-4" method="post" action="{{ url('/user/two-factor-authentication') }}">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit">Desativar segundo fator</button></form>
@endif
</div></section>
<section class="card"><div class="card-body"><h2 class="h5">Sessões ativas</h2>
<ul class="list-unstyled">@foreach($sessions as $session)<li class="mb-4">
{{ $session->ip_address ?? 'IP indisponível' }} · {{ date('d/m/Y H:i', $session->last_activity) }}
@if($session->id === session()->getId())<span class="badge bg-label-primary">Atual</span>
@else<form method="post" action="{{ route('sessions.destroy', $session->id) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit">Encerrar sessão</button></form>@endif
</li>@endforeach</ul></div></section>
</div>
@endsection
