<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sync API URL
    |--------------------------------------------------------------------------
    | Base URL of the server that exposes the /sync/* endpoints.
    */
    'api_url' => env('SYNC_API_URL', 'https://api.example.com'),

    /*
    |--------------------------------------------------------------------------
    | Resource mapping
    |--------------------------------------------------------------------------
    | Maps resource names (used in the queue) to their Eloquent model class.
    |
    | Example:
    |   'tasks' => \App\Models\Task::class,
    */
    'resource_mapping' => [],

    /*
    |--------------------------------------------------------------------------
    | Conflict resolution
    |--------------------------------------------------------------------------
    | default_strategy: server_wins | client_wins | last_write_wins | merge
    | per_resource:     override the strategy for a specific resource name.
    */
    'conflict_resolution' => [
        'default_strategy' => env('SYNC_CONFLICT_STRATEGY', 'server_wins'),
        'per_resource'     => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connectivity
    |--------------------------------------------------------------------------
    */
    'connectivity' => [
        'check_interval'   => 30,   // seconds between connectivity checks
        'auto_sync'        => true,
        'require_wifi'     => false,
        'background_sync'  => true,
        'timeout'          => 30,   // HTTP request timeout in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'batch_size'        => 50,    // items per push/pull request
        'max_queue_size'    => 1000,
        'purge_synced_after'=> 7,     // days before synced items are purged
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    */
    'max_retry_attempts' => 3,
    'retry_delay'        => 60, // seconds between retries

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    | Authentication is intentionally NOT handled by this plugin.
    | The host application is responsible for auth (tokens, sessions, etc.).
    |
    | `headers` — any HTTP headers to add to every outgoing sync request.
    |             Typical use: pass a Bearer token set by the host app.
    |
    |             Example (in your AppServiceProvider or a middleware):
    |
    |               config(['offline-sync.security.headers' => [
    |                   'Authorization' => 'Bearer ' . $user->currentAccessToken()->token,
    |               ]]);
    |
    | `require_https` — refuse to sync over plain HTTP (recommended).
    */
    'security' => [
        'require_https' => env('SYNC_REQUIRE_HTTPS', true),
        'headers'       => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'channel' => 'daily',
    ],

];
