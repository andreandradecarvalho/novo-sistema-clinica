<?php

declare(strict_types=1);

namespace App\Domain\Patients\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientConsent;
use App\Domain\Tenancy\Services\TenantContext;

final class PatientConsentPolicy
{
    public function viewAny(User $actor, Patient $patient): bool
    {
        return $actor->can('view', $patient) && app(TenantPermission::class)->allows($actor, 'consents.view');
    }

    public function create(User $actor, Patient $patient): bool
    {
        return $this->viewAny($actor, $patient) && app(TenantPermission::class)->allows($actor, 'consents.manage');
    }

    public function view(User $actor, PatientConsent $consent): bool
    {
        return (int) $consent->tenant_id === app(TenantContext::class)->id()
            && $consent->patient !== null && $this->viewAny($actor, $consent->patient);
    }
}
