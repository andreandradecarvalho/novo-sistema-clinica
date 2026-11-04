<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreateHealthQuestionGroup
{
    public function execute(array $input): HealthQuestionGroup
    {
        Gate::authorize('create', HealthQuestionGroup::class);
        try {
            return DB::transaction(function () use ($input): HealthQuestionGroup {
                $group = HealthQuestionGroup::query()->create(array_intersect_key($input, array_flip(['code', 'name', 'ordem'])));
                app(RegistryAudit::class)->record('catalog', 'health_question_group_created', $group, HealthQuestionGroup::class);

                return $group;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Já existe um grupo com este código nesta clínica.']);
        }
    }
}
