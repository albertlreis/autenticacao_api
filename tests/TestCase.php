<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use Concerns\CreatesTestDatabase;

    protected static bool $migrationsReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$migrationsReady) {
            return;
        }

        $this->ensureTestDatabaseExists();
        $this->runSharedMigrations();
        self::$migrationsReady = true;
    }

    protected function runSharedMigrations(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($database === '' || !str_ends_with($database, '_test')) {
            throw new \LogicException(
                "A suíte só pode recriar bancos dedicados com sufixo _test; recebido: '{$database}'."
            );
        }

        $this->artisan('migrate:fresh', ['--force' => true]);
    }
}
