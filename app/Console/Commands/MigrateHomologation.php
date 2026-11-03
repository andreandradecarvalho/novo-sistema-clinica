<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LocalHomologation;
use Illuminate\Console\Command;
use RuntimeException;

final class MigrateHomologation extends Command
{
    #[\Override]
    protected $signature = 'homologation:migrate';

    #[\Override]
    protected $description = 'Migrate only the isolated local homologation database';

    public function handle(LocalHomologation $homologation): int
    {
        try {
            $homologation->ensure();
        } catch (RuntimeException) {
            $this->error('This command requires the isolated local homologation database.');

            return self::FAILURE;
        }

        return $this->call('migrate', ['--force' => true]);
    }
}
