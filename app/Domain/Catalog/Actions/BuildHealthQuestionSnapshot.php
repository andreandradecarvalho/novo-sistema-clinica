<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\HealthQuestion;
use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Catalog\ValueObjects\HealthQuestionSnapshot;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class BuildHealthQuestionSnapshot
{
    public function execute(HealthQuestionGroup $group, HealthQuestion $question): HealthQuestionSnapshot
    {
        $group = HealthQuestionGroup::query()->whereKey($group->getKey())->firstOrFail();
        Gate::authorize('view', $group);
        $question = $group->questions()->whereKey($question->getKey())->firstOrFail();
        if ($question->type !== 'text' || $question->options !== null || $question->applies_to !== 'all') {
            throw ValidationException::withMessages(['question' => 'Esta configuração de pergunta ainda não tem suporte nesta etapa.']);
        }

        return new HealthQuestionSnapshot((int) $question->tenant_id, (int) $question->id, $question->code, $question->version, $question->label, $question->type, $question->options);
    }
}
