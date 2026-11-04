<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListExamTypes
{
    public function execute(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', ExamType::class);
        $exams = ExamType::query()->select(['id', 'public_id', 'code', 'name', 'is_active', 'price', 'default_duration_min', 'ordem'])
            ->orderBy('ordem')->orderBy('name')->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('catalog', 'exam_types_listed', null, ExamType::class);

        return $exams;
    }
}
