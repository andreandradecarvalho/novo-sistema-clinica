<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing') || (config('database.connections.mariadb.database') !== 'novo_sistema_clinica_testing' || ! in_array(config('database.connections.mariadb.host'), ['db', '127.0.0.1', 'localhost'], true))) {
            throw new RuntimeException('Tests require the isolated local testing database.');
        }
    }
}
