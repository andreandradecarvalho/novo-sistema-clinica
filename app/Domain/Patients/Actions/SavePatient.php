<?php

declare(strict_types=1);

namespace App\Domain\Patients\Actions;

use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientAudit;
use App\Domain\Patients\Services\PatientIdentity;
use App\Domain\Shared\ValueObjects\Cpf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SavePatient
{
    public function execute(array $input, ?Patient $patient = null): Patient
    {
        try {
            return DB::transaction(function () use ($input, $patient): Patient {
                $patient ??= new Patient;
                $event = $patient->exists ? 'patient_updated' : 'patient_created';
                $patient->fill(array_intersect_key($input, array_flip(['name', 'birth_date', 'email', 'mobile_phone'])));
                if (! empty($input['cpf'])) {
                    $cpf = new Cpf($input['cpf']);
                    $patient->cpf_hash = app(PatientIdentity::class)->hash($cpf);
                    $patient->cpf_encrypted = $cpf->digits;
                    $patient->crypto_key_version = 1;
                }
                $patient->is_partial = $patient->cpf_hash === null;
                $patient->save();
                app(PatientAudit::class)->record($event, $patient);

                return $patient;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['cpf' => 'Já existe um cadastro com este CPF nesta clínica. Revise o cadastro existente.']);
        }
    }
}
