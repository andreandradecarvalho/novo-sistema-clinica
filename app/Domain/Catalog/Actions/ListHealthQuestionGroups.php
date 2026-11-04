<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListHealthQuestionGroups
{
    public function execute(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', HealthQuestionGroup::class);
        $groups = HealthQuestionGroup::query()->select(['id', 'tenant_id', 'code', 'name', 'ordem'])->orderBy('ordem')->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('catalog', 'health_question_groups_listed', null, HealthQuestionGroup::class);

        return $groups;
    }
}
