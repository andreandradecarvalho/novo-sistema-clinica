@extends('layouts.app')
@section('title', 'Reservas do paciente')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Reservas do paciente</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
@can('create', [\App\Domain\Scheduling\Models\Appointment::class, $patient])<a class="btn btn-primary mb-6" href="{{ route('patients.appointments.create', $patient->public_id) }}">Nova reserva diária</a>@endcan
<section class="card"><h2 class="card-header h5">Reservas nas unidades autorizadas</h2><div class="table-responsive"><table class="table"><caption class="visually-hidden">Reservas diárias</caption><thead><tr><th scope="col">Data</th><th scope="col">Situação</th><th scope="col">Ações</th></tr></thead><tbody>
@forelse($appointments as $appointment)<tr><td>{{ $appointment->scheduled_date->format('d/m/Y') }}</td><td>{{ ['pre_agendado' => 'Pré-agendado', 'confirmado' => 'Confirmado', 'checked_in' => 'Check-in realizado', 'cancelado' => 'Cancelado'][$appointment->status] ?? $appointment->status }}</td><td><a href="{{ route('patients.appointments.show', [$patient->public_id, $appointment->public_id]) }}">Consultar reserva</a></td></tr>
@empty<tr><td colspan="3">Nenhuma reserva disponível.</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $appointments->links() }}</div></section>
<a class="btn btn-label-secondary mt-6" href="{{ route('patients.show', $patient->public_id) }}">Voltar ao paciente</a>
@endsection
