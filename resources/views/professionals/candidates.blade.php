@extends('layouts.app')
@section('title', 'Selecionar integrante')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Novo profissional</h1><p class="text-body-secondary">Selecione um integrante ativo que ainda não possui cadastro profissional.</p></div>
<section class="card"><h2 class="card-header h5">Integrantes disponíveis</h2><div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Integrantes disponíveis para cadastro profissional</caption><thead><tr><th scope="col">Nome</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($users as $candidate)<tr><td>{{ $candidate->name }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('professionals.registration', $candidate->public_id) }}">Selecionar</a></td></tr>@empty<tr><td colspan="2" class="text-center py-8 text-body-secondary">Nenhum integrante disponível.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $users->links('pagination::bootstrap-5') }}</div></section>
<a class="btn btn-label-secondary mt-6" href="{{ route('professionals.index') }}">Voltar aos profissionais</a>
@endsection
