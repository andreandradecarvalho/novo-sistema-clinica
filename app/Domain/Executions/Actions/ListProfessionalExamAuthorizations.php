<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\Gate;

final class ListProfessionalExamAuthorizations
{
    public function execute(Professional $professional): array
    {
        $professional = Professional::query()->whereKey($professional->getKey())->firstOrFail();
        Gate::authorize('viewAny', [ProfessionalExamAuthorization::class, $professional]);
        $grants = $professional->authorizations()->with('exam')->orderByDesc('id')->paginate(20, ['*'], 'grants_page');
        $reasons = [];
        foreach ($grants as $grant) {
            $reasons[$grant->id] = $grant->reasonSnapshot();
        }
        $exams = Gate::allows('manage', [ProfessionalExamAuthorization::class, $professional])
            ? ExamType::query()->select(['id', 'tenant_id', 'code', 'name'])->where('is_active', true)->orderBy('name')->orderBy('id')->paginate(20, ['*'], 'exams_page') : null;
        app(RegistryAudit::class)->record('professional_exam_authorizations', 'professional_exam_authorizations_listed', $professional, Professional::class);

        return compact('professional', 'grants', 'reasons', 'exams');
    }
}
