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
        $this->artisan('migrate:fresh', ['--force' => true]);
    }
}
