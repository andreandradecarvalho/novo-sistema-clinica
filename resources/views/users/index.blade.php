@extends('layouts.app')
@section('title', 'Equipe')
@section('content')
<section class="card"><div class="card-body"><h1 class="h3">Equipe da clínica</h1>
<div class="table-responsive"><table class="table"><thead><tr><th>Nome</th><th>E-mail</th><th>Situação</th></tr></thead><tbody>
@forelse($users as $member)<tr><td><a href="{{ route('users.show', $member->public_id) }}">{{ $member->name }}</a></td><td>{{ $member->email }}</td><td>{{ $member->is_active ? 'Ativo' : 'Inativo' }}</td></tr>
@empty<tr><td colspan="3">Nenhum integrante cadastrado.</td></tr>@endforelse
</tbody></table></div>{{ $users->links('pagination::bootstrap-5') }}</div></section>
@endsection
