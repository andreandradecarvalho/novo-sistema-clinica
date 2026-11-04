<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ExamPreparation;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListExamPreparations
{
    public function execute(ExamType $examType): LengthAwarePaginator
    {
        Gate::authorize('viewAny', [ExamPreparation::class, $examType]);
        $preparations = $examType->preparations()->select(['id', 'tenant_id', 'exam_type_id', 'title'])->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('catalog', 'exam_preparations_listed', $examType, ExamType::class);

        return $preparations;
    }
}
