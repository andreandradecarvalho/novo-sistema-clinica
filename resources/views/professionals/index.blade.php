@extends('layouts.app')
@section('title', 'Profissionais')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Profissionais</h1><p class="text-body-secondary">Cadastros profissionais vinculados à equipe da clínica.</p></div>
<section class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">{{ $professionals->total() }} profissionais</h2>@can('create', \App\Domain\Professionals\Models\Professional::class)<a class="btn btn-primary" href="{{ route('professionals.create') }}">Novo profissional</a>@endcan</div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Cadastros profissionais da clínica</caption><thead><tr><th scope="col">Integrante</th><th scope="col">Registro</th><th scope="col">Especialidade</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($professionals as $professional)<tr><td>{{ $professional->user?->name ?? 'Integrante indisponível' }}</td><td>{{ $professional->council ?? 'Não informado' }} {{ $professional->council_number }} {{ $professional->council_uf }}</td><td>{{ $professional->specialty ?? 'Não informada' }}</td><td><a class="btn btn-sm btn-label-primary" href="{{ route('professionals.show', $professional->id) }}">Consultar</a></td></tr>@empty<tr><td colspan="4" class="text-center py-8 text-body-secondary">Nenhum profissional cadastrado.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $professionals->links('pagination::bootstrap-5') }}</div></section>
@endsection
