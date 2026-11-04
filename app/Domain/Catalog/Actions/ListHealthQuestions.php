<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListHealthQuestions
{
    public function execute(HealthQuestionGroup $group): LengthAwarePaginator
    {
        Gate::authorize('view', $group);
        $questions = $group->questions()->select(['id', 'tenant_id', 'group_id', 'code', 'label', 'version', 'ordem'])->orderBy('ordem')->orderBy('code')->orderByDesc('version')->paginate(20);
        app(RegistryAudit::class)->record('catalog', 'health_questions_listed', $group, HealthQuestionGroup::class);

        return $questions;
    }
}
