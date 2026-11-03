@extends('layouts.app')
@section('title', 'Unidades clínicas')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Unidades clínicas</h1><p class="text-body-secondary">Locais de atendimento desta clínica.</p></div>
<section class="card"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3"><h2 class="h5 mb-0">{{ $units->total() }} unidades</h2>@can('create', \App\Domain\Tenancy\Models\Unit::class)<a class="btn btn-primary" href="{{ route('units.create') }}">Nova unidade</a>@endcan</div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Unidades de atendimento da clínica</caption><thead><tr><th scope="col">Nome</th><th scope="col">Cidade / UF</th><th scope="col">Situação</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($units as $unit)<tr><td>{{ $unit->name }}</td><td>{{ $unit->city ?? 'Não informada' }} {{ $unit->state ? ' / '.$unit->state : '' }}</td><td><span @class(['badge', 'bg-label-success' => $unit->is_active, 'bg-label-secondary' => !$unit->is_active])>{{ $unit->is_active ? 'Ativa' : 'Inativa' }}</span></td><td><a class="btn btn-sm btn-label-primary" href="{{ route('units.show', $unit->public_id) }}">Consultar</a></td></tr>@empty<tr><td colspan="4" class="text-center py-8 text-body-secondary">Nenhuma unidade cadastrada.</td></tr>@endforelse
</tbody></table></div><div class="card-body border-top">{{ $units->links('pagination::bootstrap-5') }}</div></section>
@endsection
