<?php

declare(strict_types=1);

namespace App\Domain\Identity\Auth;

use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Session\DatabaseSessionHandler;

final class TenantSessionHandler extends DatabaseSessionHandler
{
    protected function getQuery()
    {
        return parent::getQuery()->where('tenant_id', app(TenantContext::class)->id());
    }

    protected function getDefaultPayload($data)
    {
        return ['tenant_id' => app(TenantContext::class)->id()] + parent::getDefaultPayload($data);
    }
}
