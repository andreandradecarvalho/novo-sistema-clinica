<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListOrderExams
{
    public function execute(Patient $patient): LengthAwarePaginator
    {
        Gate::authorize('create', [ExamOrder::class, $patient]);

        return ExamType::query()->select(['id', 'tenant_id', 'code', 'name', 'price'])->where('scope', 'exame')->where('is_active', true)->whereNotNull('price')->where('price', '>=', 0)->orderBy('name')->orderBy('id')->paginate(20);
    }
}
