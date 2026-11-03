<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUnit;
use App\Domain\Shared\Services\RegistryAudit;

final class CompanyAudit
{
    public function record(string $event, Company|CompanyUnit|null $subject = null): void
    {
        app(RegistryAudit::class)->record('companies', $event, $subject, $subject === null ? Company::class : $subject::class);
    }
}
