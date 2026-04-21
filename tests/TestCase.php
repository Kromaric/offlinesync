<?php

namespace VendorName\OfflineSync\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use VendorName\OfflineSync\OfflineSyncServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Use a project-owned skeleton so bootstrap/cache is always present
     * and PHP's is_writable() reports correctly on all platforms (incl. Windows).
     */
    public static function applicationBasePath(): string
    {
        return __DIR__ . '/laravel';
    }

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
        // Test database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Plugin configuration
        $app['config']->set('offline-sync.api_url', 'https://api.test.com');
        $app['config']->set('offline-sync.resource_mapping', [
            'tasks' => \VendorName\OfflineSync\Tests\Fixtures\Task::class,
        ]);
    }
}
