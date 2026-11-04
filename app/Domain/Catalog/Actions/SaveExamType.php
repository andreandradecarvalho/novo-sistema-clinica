<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveExamType
{
    public function execute(array $input, ?ExamType $examType = null): ExamType
    {
        try {
            return DB::transaction(function () use ($input, $examType): ExamType {
                $examType = $examType === null ? new ExamType : ExamType::query()->whereKey($examType->getKey())->lockForUpdate()->firstOrFail();
                Gate::authorize($examType->exists ? 'update' : 'create', $examType->exists ? $examType : ExamType::class);
                $event = $examType->exists ? 'exam_type_updated' : 'exam_type_created';
                $examType->fill(array_intersect_key($input, array_flip(ExamType::EDITABLE)))->save();
                app(RegistryAudit::class)->record('catalog', $event, $examType, ExamType::class);

                return $examType;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Já existe um exame com este código nesta clínica.']);
        }
    }
}
