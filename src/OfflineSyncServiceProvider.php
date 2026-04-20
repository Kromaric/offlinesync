<?php

namespace Techparse\OfflineSync;

use Illuminate\Support\ServiceProvider;
use Techparse\OfflineSync\Commands\QueueClear;
use Techparse\OfflineSync\Commands\SyncPull;
use Techparse\OfflineSync\Commands\SyncPush;
use Techparse\OfflineSync\Commands\SyncStatus;

class OfflineSyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/offline-sync.php',
            'offline-sync'
        );

        // Bind QueueManager as singleton
        $this->app->singleton('offline-sync', function ($app) {
            return new QueueManager();
        });

        // Bind SyncEngine
        $this->app->singleton(SyncEngine::class, function ($app) {
            return new SyncEngine(
                $app->make(ConflictResolver::class),
                $app->make(ConnectivityService::class)
            );
        });

        // Bind ConflictResolver
        $this->app->singleton(ConflictResolver::class, function ($app) {
            return new ConflictResolver();
        });

        // Bind ConnectivityService
        $this->app->singleton(ConnectivityService::class, function ($app) {
            return new ConnectivityService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/offline-sync.php' => config_path('offline-sync.php'),
        ], 'offline-sync-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPush::class,
                SyncPull::class,
                SyncStatus::class,
                QueueClear::class,
            ]);
        }
    }
}
