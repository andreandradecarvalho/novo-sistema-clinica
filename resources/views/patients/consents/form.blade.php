@extends('layouts.app')
@section('title', 'Registrar manifestação')
@section('content')
<div class="page-heading"><h1 class="h4 mb-1">Registrar manifestação</h1><p class="text-body-secondary">{{ $patient->name }}</p></div>
<section class="card"><h2 class="card-header h5">Finalidade e evidência</h2><div class="card-body">
    <p>Use a finalidade, base e termo aprovados pela clínica. Registre somente a manifestação conferida com o paciente ou representante. Não inclua conteúdo clínico.</p>
    <form method="post" action="{{ route('patients.consents.store', $patient->public_id) }}">@csrf
        <div class="row">
            <div class="col-md-6"><x-input name="purpose" label="Finalidade" value="" maxlength="80" /></div>
            <div class="col-md-6"><x-input name="legal_basis" label="Base de tratamento informada pela clínica" value="" maxlength="80" /></div>
        </div>
        <x-input name="policy_version" label="Versão do termo" value="" maxlength="40" />
        @foreach(['policy_text' => ['Texto exato do termo apresentado', 16000, 8], 'evidence_note' => ['Como a manifestação foi conferida (sem dados clínicos)', 4000, 4]] as $field => [$label, $limit, $rows])
        <div class="mb-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label>
            <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ $rows }}" maxlength="{{ $limit }}" required class="form-control @error($field) is-invalid @enderror" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror></textarea>
            @error($field)<div id="{{ $field }}-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        @endforeach
        <div class="mb-6"><label for="decision" class="form-label">Manifestação</label>
            <select id="decision" name="decision" required class="form-select @error('decision') is-invalid @enderror" @error('decision') aria-invalid="true" aria-describedby="decision-error" @enderror>
                <option value="">Selecione a manifestação conferida</option>
                @foreach($decisions as $decision)<option value="{{ $decision->value }}">{{ $decision->label() }}</option>@endforeach
            </select>
            @error('decision')<div id="decision-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="form-check mb-6"><input type="checkbox" class="form-check-input @error('attested') is-invalid @enderror" name="attested" id="attested" value="1" required @error('attested') aria-invalid="true" aria-describedby="attested-error" @enderror><label for="attested" class="form-check-label">Conferi a identidade e a manifestação que estou registrando agora.</label>
            @error('attested')<div id="attested-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <p class="form-text">Cada lançamento preserva o anterior. Os campos de texto precisam ser preenchidos novamente em caso de erro de validação.</p>
        <div class="d-flex gap-3"><button type="submit" class="btn btn-primary">Registrar manifestação</button><a class="btn btn-label-secondary" href="{{ route('patients.consents.index', $patient->public_id) }}">Cancelar</a></div>
    </form>
</div></section>
@endsection
