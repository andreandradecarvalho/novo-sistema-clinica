<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\HealthQuestion;
use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PublishHealthQuestion
{
    public function execute(HealthQuestionGroup $group, array $input, ?HealthQuestion $source = null): HealthQuestion
    {
        try {
            return DB::transaction(function () use ($group, $input, $source): HealthQuestion {
                $group = HealthQuestionGroup::query()->whereKey($group->getKey())->lockForUpdate()->firstOrFail();
                Gate::authorize('publish', $group);
                $question = new HealthQuestion;
                $question->group()->associate($group);
                if ($source === null) {
                    $question->fill(array_intersect_key($input, array_flip(['code', 'label', 'ordem'])));
                    $question->version = 1;
                } else {
                    $source = $group->questions()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
                    $latest = $group->questions()->where('code', $source->code)->orderByDesc('version')->lockForUpdate()->firstOrFail();
                    if ($source->type !== 'text' || $source->options !== null || $source->applies_to !== 'all') {
                        throw ValidationException::withMessages(['label' => 'Esta configuração de pergunta ainda não pode ser versionada nesta tela.']);
                    }
                    if ($latest->id !== $source->id || $source->version >= 65535) {
                        throw ValidationException::withMessages(['label' => 'Reabra a versão mais recente. O limite de versões não pode ser excedido.']);
                    }
                    $question->fill(['code' => $source->code, 'label' => $input['label'], 'ordem' => $source->ordem]);
                    $question->version = $source->version + 1;
                }
                // Only free text for all patients is supported by this administrative delivery.
                $question->type = 'text';
                $question->options = null;
                $question->applies_to = 'all';
                $question->is_active = true;
                $question->save();
                app(RegistryAudit::class)->record('catalog', 'health_question_version_created', $question, HealthQuestion::class);

                return $question;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Já existe uma pergunta com este código nesta clínica.']);
        }
    }
}
