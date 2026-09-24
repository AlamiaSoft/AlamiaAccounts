<?php

namespace Alamia360\Tests;

use Alamia360\Alamia360ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [Alamia360ServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite in-memory for all tests — zero infrastructure required.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Keep persistence in-memory during unit tests for speed.
        // Integration tests that need DB persistence use RefreshDatabase.
        $app['config']->set('alamia360.persistence.driver', 'memory');
        $app['config']->set('alamia360.observation.persist', false);
        $app['config']->set('alamia360.observation.queue', false);
    }
}
