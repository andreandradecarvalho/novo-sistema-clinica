<?php

declare(strict_types=1);

namespace App\Domain\Identity\Auth;

use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;

final class TenantTokenRepository extends DatabaseTokenRepository
{
    protected function getTable()
    {
        return parent::getTable()->where('tenant_id', app(TenantContext::class)->id());
    }

    protected function getPayload($email, #[\SensitiveParameter] $token)
    {
        return ['tenant_id' => app(TenantContext::class)->id()] + parent::getPayload($email, $token);
    }
}
