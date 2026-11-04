<?php

declare(strict_types=1);

namespace App\Domain\Patients\Actions;

use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientAudit;
use App\Domain\Patients\Services\PatientIdentity;
use App\Domain\Shared\ValueObjects\Cpf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchPatients
{
    public function criteria(array $input): array
    {
        if ($input['mode'] === 'cpf') {
            return ['mode' => 'cpf', 'cpf_hash' => app(PatientIdentity::class)->hash(new Cpf($input['cpf']))];
        }

        return ['mode' => 'name', 'normalized_name' => PatientIdentity::normalizeName($input['name']), 'birth_date' => $input['birth_date'] ?? null];
    }

    public function execute(array $criteria): LengthAwarePaginator
    {
        $query = Patient::query()->select(['id', 'public_id', 'name', 'birth_date', 'is_partial']);
        if ($criteria['mode'] === 'cpf') {
            $query->where('cpf_hash', $criteria['cpf_hash']);
        } else {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $criteria['normalized_name']);
            $query->whereRaw("patients.normalized_name LIKE ? ESCAPE '!'", [$literal.'%']);
            if ($criteria['birth_date'] !== null) {
                $query->where('birth_date', $criteria['birth_date']);
            }
        }
        $patients = $query->orderBy('normalized_name')->orderBy('id')->paginate(20);
        app(PatientAudit::class)->record('patients_searched');

        return $patients;
    }
}
