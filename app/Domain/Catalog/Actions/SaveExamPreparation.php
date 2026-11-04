<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ExamPreparation;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SaveExamPreparation
{
    public function execute(ExamType $examType, array $input, ?ExamPreparation $preparation = null): ExamPreparation
    {
        return DB::transaction(function () use ($examType, $input, $preparation): ExamPreparation {
            $examType = ExamType::query()->whereKey($examType->getKey())->lockForUpdate()->firstOrFail();
            if ($preparation === null) {
                Gate::authorize('create', [ExamPreparation::class, $examType]);
                $preparation = new ExamPreparation;
                $preparation->examType()->associate($examType);
            } else {
                $preparation = $examType->preparations()->whereKey($preparation->getKey())->lockForUpdate()->firstOrFail();
                $preparation->setRelation('examType', $examType);
                Gate::authorize('update', $preparation);
            }
            $event = $preparation->exists ? 'exam_preparation_updated' : 'exam_preparation_created';
            $preparation->fill(array_intersect_key($input, array_flip(['title', 'instructions'])))->save();
            app(RegistryAudit::class)->record('catalog', $event, $preparation, ExamPreparation::class);

            return $preparation;
        });
    }
}
