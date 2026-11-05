<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\Gate;

final class ReadExamOrder
{
    public function execute(Patient $patient, ExamOrder $order): ExamOrder
    {
        $order = $patient->orders()->whereKey($order->getKey())->firstOrFail();
        Gate::authorize('view', $order);
        $order->load(['items' => fn ($query) => $query->select(['id', 'tenant_id', 'exam_order_id', 'quantity', 'unit_price', 'discount_amount', 'status', 'description_snapshot'])->orderBy('id')]);
        app(RegistryAudit::class)->record('orders', 'exam_order_viewed', $order, ExamOrder::class);

        return $order;
    }
}
