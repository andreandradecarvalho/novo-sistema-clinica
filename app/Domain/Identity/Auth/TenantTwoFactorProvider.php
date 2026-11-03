<?php

declare(strict_types=1);

namespace App\Domain\Identity\Auth;

use App\Domain\Tenancy\Services\TenantContext;
use Laravel\Fortify\TwoFactorAuthenticationProvider;

final class TenantTwoFactorProvider extends TwoFactorAuthenticationProvider
{
    public function verify($secret, $code)
    {
        $key = app(TenantContext::class)->key('2fa.used:'.hash('sha256', $secret.'|'.$code));
        $timestamp = $this->engine->verifyKeyNewer($secret, $code, $this->cache?->get($key));
        if ($timestamp === false) {
            return false;
        }
        $this->cache?->put($key, $timestamp === true ? $this->engine->getTimestamp() : $timestamp, ($this->engine->getWindow() ?: 1) * 60);

        return true;
    }
}
