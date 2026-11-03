@extends('layouts.base')
@section('title', 'Página indisponível')
@section('body')
<main id="main-content" class="container-xxl container-p-y"><div class="misc-wrapper text-center d-flex flex-column align-items-center justify-content-center min-vh-100">
    <h1 class="h3 mb-2">Página indisponível</h1><p class="mb-6">Confira o endereço da sua clínica.</p>
    @if(app(\App\Domain\Tenancy\Services\TenantContext::class)->current())<a href="{{ url('/') }}" class="btn btn-primary mb-6">Voltar ao início</a>@endif
    <img src="{{ Vite::asset('docs/layout/assets/img/illustrations/page-misc-error.png') }}" alt="" width="420" height="300" class="img-fluid object-fit-contain">
</div></main>
@endsection
