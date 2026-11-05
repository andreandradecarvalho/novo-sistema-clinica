<?php

declare(strict_types=1);

namespace App\Domain\Executions\Services;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TenantPermission;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Validation\ValidationException;

final class ProfessionalExamAccess
{
    // Type-level authorization only: future execution Policies must also authorize patient/visit/unit/assignment.
    public function allows(User $actor, ExamType $exam): bool
    {
        $tenant = app(TenantContext::class)->id();
        if ((int) $actor->tenant_id !== $tenant || (int) $exam->tenant_id !== $tenant) {
            return false;
        }
        $actor = User::query()->whereKey($actor->getKey())->where('is_active', true)->first();
        if ($actor === null || ! app(TenantPermission::class)->allows($actor, 'clinical.access')
            || ! app(TenantPermission::class)->allows($actor, 'executions.perform')
            || ! ExamType::query()->whereKey($exam->getKey())->where('is_active', true)->exists()) {
            return false;
        }
        $professional = Professional::query()->where('user_id', $actor->id)->first();
        if ($professional === null) {
            return false;
        }
        $grant = ProfessionalExamAuthorization::query()->where('professional_id', $professional->id)->where('exam_type_id', $exam->id)
            ->whereNull('revoked_at')->where('granted_at', '<=', now('UTC'))->first();
        if ($grant === null) {
            return false;
        }
        try {
            $grant->reasonSnapshot();
        } catch (ValidationException) {
            return false;
        }

        return true;
    }
}
