@extends('layouts.app')
@section('title', 'Segurança da conta')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Segurança da conta</h1><p class="text-body-secondary mb-0">Proteja seu acesso e acompanhe as sessões conectadas.</p></div>
<nav class="nav-align-top mb-6" aria-label="Configurações da conta"><ul class="nav nav-pills flex-column flex-md-row"><li class="nav-item"><a class="nav-link active" href="{{ route('security') }}" aria-current="page"><x-icon name="shield-lock" class="me-2" />Segurança</a></li></ul></nav>
<div class="row g-6">
    <div class="col-lg-6">
        <section class="card h-100"><h2 class="card-header h5">Alterar senha</h2><div class="card-body">
            @if(auth()->user()->must_change_password)<div class="alert alert-warning" role="status">Altere a senha inicial antes de acessar a clínica.</div>@endif
            <form method="post" action="{{ url('/user/password') }}">@csrf @method('PUT')
                <x-input name="current_password" label="Senha atual" type="password" autocomplete="current-password" error-bag="updatePassword" />
                <x-input name="password" label="Nova senha" type="password" autocomplete="new-password" error-bag="updatePassword" />
                <x-input name="password_confirmation" label="Confirme a nova senha" type="password" autocomplete="new-password" error-bag="updatePassword" />
                <p class="small text-body-secondary mb-6">Use pelo menos 12 caracteres, letras maiúsculas, minúsculas e números.</p>
                <button class="btn btn-primary" type="submit">Alterar senha</button>
            </form>
        </div></section>
    </div>
    <div class="col-lg-6">
        <section class="card h-100"><h2 class="card-header h5">Autenticação em dois fatores</h2><div class="card-body">
            <span class="avatar mb-4"><span class="avatar-initial rounded bg-label-primary"><x-icon name="shield-lock" class="icon-26px" /></span></span>
            @if(!auth()->user()->two_factor_secret)
                <p>Adicione uma camada de proteção à sua conta com um aplicativo autenticador.</p><p class="text-body-secondary mb-6">O segundo fator é obrigatório para acesso clínico.</p>
                <form method="post" action="{{ url('/user/two-factor-authentication') }}">@csrf<button class="btn btn-primary" type="submit">Ativar segundo fator</button></form>
            @else
                @if(!auth()->user()->two_factor_confirmed_at)
                    <p>Leia o QR code com seu aplicativo autenticador e confirme o código.</p>
                    <div class="qr-code mb-6">{!! auth()->user()->twoFactorQrCodeSvg() !!}</div>
                    <form method="post" action="{{ url('/user/confirmed-two-factor-authentication') }}">@csrf
                        <x-input name="code" label="Código de verificação" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" error-bag="confirmTwoFactorAuthentication" />
                        <button class="btn btn-primary" type="submit">Confirmar ativação</button>
                    </form>
                @else
                    <p class="alert alert-success">Segundo fator confirmado.</p>
                    <details><summary class="text-primary">Códigos de recuperação — guarde em local seguro</summary><ul class="recovery-codes mt-4">@foreach(auth()->user()->recoveryCodes() as $code)<li><code>{{ $code }}</code></li>@endforeach</ul></details>
                    <form class="mt-6" method="post" action="{{ url('/user/two-factor-recovery-codes') }}">@csrf<button class="btn btn-label-primary" type="submit">Gerar novos códigos</button></form>
                @endif
                <form class="mt-6" method="post" action="{{ url('/user/two-factor-authentication') }}">@csrf @method('DELETE')<button class="btn btn-label-danger" type="submit">Desativar segundo fator</button></form>
            @endif
        </div></section>
    </div>
    <div class="col-12">
        <section class="card"><div class="card-header"><h2 class="h5 mb-1">Sessões ativas</h2><p class="text-body-secondary mb-0">Encerre uma sessão caso não reconheça o acesso.</p></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><caption class="visually-hidden">Sessões conectadas à sua conta</caption>
                <thead><tr><th scope="col">Conexão</th><th scope="col">Última atividade</th><th scope="col" class="text-end">Ação</th></tr></thead>
                <tbody>@forelse($sessions as $session)<tr>
                    <td><span class="d-inline-flex align-items-center gap-2"><x-icon name="device-desktop" class="text-body-secondary" />{{ $session->ip_address ?? 'IP indisponível' }}</span></td>
                    <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->timezone(app(\App\Domain\Tenancy\Services\TenantContext::class)->require()->timezone)->format('d/m/Y H:i') }}</td>
                    <td class="text-end">@if($session->id === session()->getId())<span class="badge bg-label-primary">Sessão atual</span>
                        @else<form method="post" action="{{ route('sessions.destroy', $session->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-label-danger" type="submit">Encerrar sessão</button></form>@endif
                    </td>
                </tr>@empty<tr><td colspan="3" class="text-center text-body-secondary py-8">Nenhuma sessão adicional encontrada.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    </div>
</div>
@endsection
