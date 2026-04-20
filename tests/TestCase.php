<?php

namespace VendorName\OfflineSync\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use VendorName\OfflineSync\OfflineSyncServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/Migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            OfflineSyncServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configuration de test
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configuration du plugin
        $app['config']->set('offline-sync.api_url', 'https://api.test.com');
        $app['config']->set('offline-sync.resource_mapping', [
            'tasks' => \VendorName\OfflineSync\Tests\Fixtures\Task::class,
        ]);
    }
}
