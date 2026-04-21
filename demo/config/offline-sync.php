<?php

return [
    // API URL
    'api_url' => env('SYNC_API_URL', 'https://api.todo-demo.test'),

    // Resource mapping
    'resource_mapping' => [
        'tasks' => \App\Models\Task::class,
    ],

    // Conflict resolution
    'conflict_resolution' => [
        'default_strategy' => 'last_write_wins',
        'per_resource' => [
            'tasks' => 'last_write_wins',
        ],
    ],

    // Connectivity
    'connectivity' => [
        'check_interval' => 30,
        'auto_sync' => true,
        'require_wifi' => false,
        'background_sync' => true,
        'timeout' => 30,
    ],

    // Performance
    'performance' => [
        'batch_size' => 50,
        'max_queue_size' => 1000,
        'purge_synced_after' => 7,
    ],

    // Retry
    'max_retry_attempts' => 3,
    'retry_delay' => 60,

    // Security
    // The plugin is auth-agnostic. Pass any HTTP headers (e.g. a Bearer token)
    // that every outgoing sync request should carry. The host application is
    // responsible for setting this at runtime — see AppServiceProvider.
    'security' => [
        'require_https' => env('SYNC_REQUIRE_HTTPS', true),
        'headers'       => [],   // populated at runtime by AppServiceProvider
    ],

    // Logging
    'logging' => [
        'enabled' => true,
        'channel' => 'daily',
    ],
];
