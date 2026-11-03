<?php

declare(strict_types=1);

namespace App\Domain\Professionals\Actions;

use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;

final class ReadProfessional
{
    public function execute(Professional $professional, string $event): Professional
    {
        $professional->load('user:id,tenant_id,name,is_active');
        app(RegistryAudit::class)->record('professionals', $event, $professional, Professional::class);

        return $professional;
    }
}
