@extends('layouts.app')
@section('content')
<section class="card"><div class="card-body"><h1 class="h3">{{ $subject->name }}</h1><p>{{ $subject->email }}</p><a href="{{ route('users.index') }}">Voltar à equipe</a></div></section>
@endsection
