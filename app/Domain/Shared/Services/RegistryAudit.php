<?php

declare(strict_types=1);

namespace App\Domain\Shared\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RegistryAudit
{
    public function record(string $domain, string $event, ?Model $subject, string $subjectType, ?int $previousLinkId = null): void
    {
        $this->write($domain, $event, $subject, $subjectType, $previousLinkId === null ? null : ['previous_link_id' => $previousLinkId]);
    }

    public function recordMemberUnitChange(User $subject, ?int $previousUnitId): void
    {
        $this->write('users', 'member_unit_changed', $subject, User::class, [
            'previous_unit_id' => $previousUnitId,
            'unit_id' => $subject->unit_id === null ? null : (int) $subject->unit_id,
        ]);
    }

    /** @param array<string, int|null>|null $references */
    private function write(string $domain, string $event, ?Model $subject, string $subjectType, ?array $references): void
    {
        DB::table('activity_log')->insert([
            'tenant_id' => app(TenantContext::class)->id(), 'log_name' => $domain,
            'event' => $event, 'description' => $event,
            'subject_type' => $subjectType, 'subject_id' => $subject?->getKey(),
            'properties' => $references === null ? null : json_encode($references, JSON_THROW_ON_ERROR),
            'causer_type' => User::class, 'causer_id' => auth()->id(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
