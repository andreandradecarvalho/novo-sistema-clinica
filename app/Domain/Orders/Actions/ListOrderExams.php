<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Companies\Models\Company;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final class ListOrderExams
{
    public function execute(Patient $patient, ?Company $payer = null): LengthAwarePaginator
    {
        if ($payer !== null) {
            $payer = Company::query()->whereKey($payer->getKey())->firstOrFail();
            Gate::authorize('createForCompany', [ExamOrder::class, $patient, $payer]);
            if (! $payer->is_active) {
                throw (new ModelNotFoundException)->setModel(Company::class);
            }
        } else {
            Gate::authorize('create', [ExamOrder::class, $patient]);
        }

        return ExamType::query()->select(['id', 'tenant_id', 'code', 'name', 'price'])->where('scope', 'exame')->where('is_active', true)->whereNotNull('price')->where('price', '>=', 0)->orderBy('name')->orderBy('id')->paginate(20);
    }
}
