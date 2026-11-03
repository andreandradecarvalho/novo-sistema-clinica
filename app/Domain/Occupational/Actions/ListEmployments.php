<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListEmployments
{
    public function execute(Patient $patient): LengthAwarePaginator
    {
        $links = $patient->employments()->select(['id', 'tenant_id', 'patient_id', 'company_id', 'company_unit_id', 'registration_number', 'job_title', 'department', 'starts_on', 'ends_on', 'termination_date', 'context_encrypted'])
            ->with(['company:id,tenant_id,name,is_active,deleted_at', 'companyUnit:id,tenant_id,company_id,name'])->orderByDesc('starts_on')->orderByDesc('id')->paginate(20);
        app(RegistryAudit::class)->record('employments', 'employments_listed', $patient, Patient::class);

        return $links;
    }
}
