@extends('layouts.app')
@section('content')
<section class="card"><div class="card-body"><h1 class="h3">Olá, {{ auth()->user()->name }}</h1>
<p class="mb-0">Sua clínica está configurada. Os módulos de atendimento serão disponibilizados nas próximas etapas.</p>
</div></section>
@endsection
