<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LocalHomologation
{
    public function ensure(): void
    {
        if (! app()->environment('staging') || config('database.default') !== 'mariadb'
            || ! in_array(config('database.connections.mariadb.url'), [null, ''], true)
            || config('database.connections.mariadb.host') !== 'db'
            || config('database.connections.mariadb.database') !== 'novo_sistema_clinica_homologacao') {
            throw new RuntimeException('The isolated local homologation database is required.');
        }
        // Read the resolved connection configuration before allowing any query or fixture mutation.
        $connection = DB::connection();
        if ($connection->getConfig('host') !== 'db' || $connection->getDatabaseName() !== 'novo_sistema_clinica_homologacao') {
            throw new RuntimeException('Resolved database does not match local homologation.');
        }
    }
}
