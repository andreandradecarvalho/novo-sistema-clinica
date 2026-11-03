<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\MemberSessions;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssignMemberUnit
{
    public function execute(User $subject, ?int $unitId): bool
    {
        return DB::transaction(function () use ($subject, $unitId): bool {
            $member = User::query()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
            if (! $member->is_active) {
                throw ValidationException::withMessages(['unit_id' => 'Selecione um integrante ativo.']);
            }
            if ($unitId !== null && ! Unit::query()->whereKey($unitId)->where('is_active', true)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['unit_id' => 'Selecione uma unidade ativa desta clínica.']);
            }
            $previousUnitId = $member->unit_id === null ? null : (int) $member->unit_id;
            if ($previousUnitId === $unitId) {
                return false;
            }
            $member->forceFill(['unit_id' => $unitId, 'remember_token' => Str::random(60)])->save();
            app(MemberSessions::class)->revokeOthers((int) $member->getKey());
            app(RegistryAudit::class)->recordMemberUnitChange($member, $previousUnitId);

            return true;
        });
    }
}
