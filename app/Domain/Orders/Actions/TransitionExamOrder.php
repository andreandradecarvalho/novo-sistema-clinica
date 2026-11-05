<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Identity\Models\User;
use App\Domain\Orders\Enums\OrderTransition;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TransitionExamOrder
{
    public function execute(Patient $patient, ExamOrder $order, OrderTransition $transition, string $expectedStatus, ?string $reason = null): ExamOrder
    {
        Validator::make(['expected_status' => $expectedStatus, 'reason' => $reason], [
            'expected_status' => ['required', 'in:draft,authorized'],
            'reason' => $transition === OrderTransition::Cancel ? ['required', 'string', 'max:1000'] : ['prohibited'],
        ])->validate();

        return DB::transaction(function () use ($patient, $order, $transition, $expectedStatus, $reason): ExamOrder {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            $order = $patient->orders()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($transition->value, $order);
            if ($order->status !== $expectedStatus || ($transition === OrderTransition::Authorize && $order->status !== 'draft')) {
                $this->refuse();
            }
            $items = $order->items()->select(['id', 'tenant_id', 'exam_order_id', 'exam_type_id', 'status'])->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty() || $items->contains(fn ($item) => $item->status !== $order->status)
                || ($order->status === 'draft' && $order->authorized_at !== null)
                || ($order->status === 'authorized' && $order->authorized_at === null)) {
                $this->refuse();
            }
            $context = $order->contextSnapshot();
            if (isset($context['cancellation']) || ($order->status === 'draft' && isset($context['authorization']))
                || ($order->status === 'authorized' && ! isset($context['authorization']))) {
                $this->refuse();
            }
            if ($transition === OrderTransition::Authorize) {
                $examIds = $items->pluck('exam_type_id')->unique()->all();
                $activeExams = ExamType::query()->select(['id', 'tenant_id'])->whereIn('id', $examIds)->where('scope', 'exame')->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
                if ($activeExams->count() !== count($examIds)) {
                    throw ValidationException::withMessages(['transition' => 'Há exame indisponível no catálogo. Revise o pedido antes de autorizar.']);
                }
            }
            $now = now('UTC');
            $context[$transition === OrderTransition::Authorize ? 'authorization' : 'cancellation'] = [
                'actor_id' => (int) $actor->id, 'occurred_at' => $now->toIso8601String(), 'from_status' => $order->status,
            ];
            if ($transition === OrderTransition::Cancel) {
                $context['cancellation']['reason'] = trim((string) $reason);
            }
            $status = $transition === OrderTransition::Authorize ? 'authorized' : 'canceled';
            $tenantId = app(TenantContext::class)->id();
            // Deliberate bulk writes: only state/evidence fields, exact tenant/owner/previous state.
            $changed = DB::table('exam_orders')->where('tenant_id', $tenantId)->where('id', $order->id)->where('patient_id', $patient->id)->where('status', $expectedStatus)
                ->update(['status' => $status, 'authorized_at' => $transition === OrderTransition::Authorize ? $now : $order->authorized_at,
                    'context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR)), 'updated_at' => $now]);
            $itemChanges = DB::table('exam_order_items')->where('tenant_id', $tenantId)->where('exam_order_id', $order->id)->whereIn('id', $items->modelKeys())->where('status', $expectedStatus)
                ->update(['status' => $status, 'updated_at' => $now]);
            if ($changed !== 1 || $itemChanges !== $items->count()) {
                $this->refuse();
            }
            $order->refresh();
            app(RegistryAudit::class)->record('orders', 'exam_order_'.$status, $order, ExamOrder::class);

            return $order;
        });
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['transition' => 'O pedido mudou ou não permite esta operação. Reabra a consulta.']);
    }
}
