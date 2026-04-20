<?php

return [
    // API URL
    'api_url' => env('SYNC_API_URL', 'https://api.example.com'),

    // Resource mapping: 'resource-name' => ModelClass::class
    'resource_mapping' => [],

    // Conflict resolution
    'conflict_resolution' => [
        'default_strategy' => env('SYNC_CONFLICT_STRATEGY', 'server_wins'),
        'per_resource' => [],
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
    'security' => [
        'encrypt_queue' => env('SYNC_ENCRYPT_QUEUE', false),
        'auth_method' => env('SYNC_AUTH_METHOD', 'bearer'),
        'require_https' => env('SYNC_REQUIRE_HTTPS', true),
    ],

    // Logging
    'logging' => [
        'enabled' => true,
        'channel' => 'daily',
    ],
];
