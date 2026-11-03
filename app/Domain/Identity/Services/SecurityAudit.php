<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class SecurityAudit
{
    public function __construct(private TenantContext $context) {}

    public function record(string $event, ?User $user): void
    {
        DB::table('activity_log')->insert([
            'tenant_id' => $this->context->id(), 'log_name' => 'security',
            'description' => $event, 'event' => $event,
            'causer_type' => $user === null ? null : User::class, 'causer_id' => $user?->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
