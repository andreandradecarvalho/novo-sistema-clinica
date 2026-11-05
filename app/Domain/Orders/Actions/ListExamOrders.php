<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListExamOrders
{
    public function execute(Patient $patient): LengthAwarePaginator
    {
        Gate::authorize('viewAny', [ExamOrder::class, $patient]);
        $orders = $patient->orders()->select(['id', 'tenant_id', 'patient_id', 'public_id', 'status', 'created_at'])
            ->when(! Gate::allows('viewAny', Company::class), fn ($query) => $query->where('payer_type', 'patient'))
            ->orderByDesc('id')->paginate(20);
        app(RegistryAudit::class)->record('orders', 'exam_orders_listed', $patient, Patient::class);

        return $orders;
    }
}
