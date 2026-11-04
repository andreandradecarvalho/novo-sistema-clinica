<?php

declare(strict_types=1);

namespace App\Domain\Patients\Services;

use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientMerge;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class PatientIdentityGroup
{
    /** @return list<int> */
    public function memberIds(Patient $survivor, bool $lock = false): array
    {
        if ((int) $survivor->tenant_id !== app(TenantContext::class)->id()) {
            throw (new ModelNotFoundException)->setModel(Patient::class);
        }
        $root = Patient::query()->whereKey($survivor->getKey());
        if ($lock) {
            $root->lockForUpdate();
        }
        $root->firstOrFail(['id']);
        $origins = Patient::onlyTrashed()->whereIn('id', PatientMerge::query()->where('survivor_patient_id', $survivor->getKey())->select('merged_patient_id'))->orderBy('id');
        if ($lock) {
            $origins->lockForUpdate();
        }

        return [(int) $survivor->getKey(), ...$origins->pluck('id')->map(fn ($id): int => (int) $id)->all()];
    }

    public function contains(Patient $survivor, int $patientId): bool
    {
        if ((int) $survivor->tenant_id !== app(TenantContext::class)->id() || $survivor->trashed()) {
            return false;
        }
        if ((int) $survivor->getKey() === $patientId) {
            return true;
        }

        return PatientMerge::query()->where('survivor_patient_id', $survivor->getKey())->where('merged_patient_id', $patientId)
            ->whereHas('mergedPatient', fn ($query) => $query->whereNotNull('deleted_at'))->exists();
    }
}
