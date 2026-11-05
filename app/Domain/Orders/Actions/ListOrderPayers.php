<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListOrderPayers
{
    public function execute(Patient $patient): LengthAwarePaginator
    {
        Gate::authorize('chooseCompany', [ExamOrder::class, $patient]);
        $companies = Company::query()->select(['id', 'tenant_id', 'public_id', 'name'])->where('is_active', true)->orderBy('name')->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('orders', 'order_payers_listed', $patient, Patient::class);

        return $companies;
    }
}
