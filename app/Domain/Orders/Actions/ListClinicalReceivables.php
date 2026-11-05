<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\Gate;

final class ListClinicalReceivables
{
    public function execute(Patient $patient, ExamOrder $order): ExamOrder
    {
        $order = $patient->orders()->whereKey($order->getKey())->firstOrFail();
        Gate::authorize('viewAny', [ClinicalReceivable::class, $order]);
        $order->load(['receivables' => fn ($query) => $query->select(['id', 'tenant_id', 'exam_order_id', 'amount', 'due_date', 'status', 'settled_at', 'transition_encrypted', 'company_id', 'payer_type', 'external_ref'])->orderBy('id')]);
        foreach ($order->receivables as $receivable) {
            $receivable->transitionSnapshot();
        }
        app(RegistryAudit::class)->record('receivables', 'clinical_receivables_viewed', $order, ExamOrder::class);

        return $order;
    }
}
