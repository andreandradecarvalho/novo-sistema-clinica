<?php

declare(strict_types=1);

namespace App\Domain\Executions\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Patients\Models\Patient;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Visits\Models\Visit;
use Illuminate\Validation\ValidationException;

final class ExecutionWorkflow
{
    /** @return array{Visit, User, Unit} */
    public function lock(Patient $patient, Visit $visit): array
    {
        $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
        $visit = $patient->visits()->whereKey($visit->getKey())->firstOrFail();
        $appointment = $patient->appointments()->whereKey($visit->appointment_id)->lockForUpdate()->firstOrFail();
        $order = $patient->orders()->whereKey($visit->exam_order_id)->lockForUpdate()->firstOrFail();
        $unit = Unit::query()->withTrashed()->whereKey($visit->unit_id)->lockForUpdate()->firstOrFail();
        $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
        $visit = $patient->visits()->whereKey($visit->getKey())->lockForUpdate()->firstOrFail();
        $visit->setRelation('appointment', $appointment)->setRelation('order', $order);

        return [$visit, $actor, $unit];
    }

    public function assertOpen(Visit $visit): void
    {
        $visit->attendanceSnapshot();
        $order = $visit->order;
        $context = $order?->contextSnapshot();
        if (! in_array($visit->status, ['open', 'in_progress'], true) || $order === null || ! in_array($order->status, ['authorized', 'in_progress', 'completed'], true) || $order->authorized_at === null
            || ! isset($context['authorization']) || isset($context['cancellation'])) {
            throw ValidationException::withMessages(['execution' => 'O atendimento ou pedido não permite alterar esta tentativa.']);
        }
    }
}
