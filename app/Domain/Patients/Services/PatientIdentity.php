<?php

declare(strict_types=1);

namespace App\Domain\Patients\Services;

use App\Domain\Shared\ValueObjects\Cpf;
use App\Domain\Tenancy\Services\TenantContext;
use RuntimeException;

final readonly class PatientIdentity
{
    public function hash(Cpf $cpf): string
    {
        $key = config('identity.cpf_search_key');
        if (! is_string($key) || ! preg_match('/\A[0-9a-f]{64}\z/', $key)) {
            throw new RuntimeException('Configure the private CPF search key before using patient identity.');
        }
        $tenantKey = hash_hkdf('sha256', hex2bin($key), 32, 'cpf:v1:tenant:'.app(TenantContext::class)->id());

        return hash_hmac('sha256', $cpf->digits, $tenantKey);
    }
}
