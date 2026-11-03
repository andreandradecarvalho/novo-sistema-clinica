@extends('layouts.app')
@section('title', 'Equipe')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Equipe da clínica</h1><p class="text-body-secondary mb-0">Integrantes e situação de acesso à clínica.</p></div>
<section class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3"><h2 class="h5 mb-0">Integrantes</h2><span class="badge bg-label-primary">{{ $users->total() }} {{ $users->total() === 1 ? 'integrante' : 'integrantes' }}</span></div>
    <div class="card-datatable table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="visually-hidden">Integrantes da equipe desta clínica</caption>
            <thead><tr><th scope="col">Integrante</th><th scope="col">E-mail</th><th scope="col">Situação</th><th scope="col" class="text-end">Ações</th></tr></thead>
            <tbody>
                @forelse($users as $member)
                <tr>
                    <td><div class="d-flex align-items-center gap-3"><span class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-secondary">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->name, 0, 1)) }}</span></span><a href="{{ route('users.show', $member->public_id) }}" class="fw-medium text-heading text-nowrap">{{ $member->name }}</a></div></td>
                    <td>{{ $member->email }}</td>
                    <td><span @class(['badge', 'bg-label-success' => $member->is_active, 'bg-label-secondary' => !$member->is_active])>{{ $member->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                    <td class="text-end"><a href="{{ route('users.show', $member->public_id) }}" class="btn btn-sm btn-text-secondary" aria-label="Ver integrante {{ $member->name }}"><x-icon name="eye" class="icon-20px" /></a></td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center py-12 text-body-secondary">Nenhum integrante cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body border-top">{{ $users->links('pagination::bootstrap-5') }}</div>
</section>
@endsection
