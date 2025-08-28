<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

trait UsesIsolatedMigrations
{
    use RefreshDatabase;

    /**
     * Override the default method to run migrations from a specific path.
     * @return void
     */
    protected function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations_isolated',
            '--realpath' => true,
        ]);

        $this . app[Kernel::class]->setArtisan(null);

        $this . beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }
}
