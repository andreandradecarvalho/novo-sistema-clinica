<?php

declare(strict_types=1);

namespace App\Domain\Visits\Actions;

use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Support\Facades\Gate;

final class ReadVisit
{
    public function execute(Patient $patient, Visit $visit): Visit
    {
        $visit = $patient->visits()->whereKey($visit->getKey())->firstOrFail();
        Gate::authorize('view', $visit);
        $visit->attendanceSnapshot();
        app(RegistryAudit::class)->record('visits', 'visit_viewed', $visit, Visit::class);

        return $visit;
    }
}
