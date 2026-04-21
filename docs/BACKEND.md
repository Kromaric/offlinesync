# Backend Guide — Laravel API Configuration

Complete guide to configuring the Laravel backend that handles synchronization with mobile apps.

---

## 📋 Table of contents

1. [Architecture](#architecture)
2. [Initial setup](#initial-setup)
3. [Sync controller](#sync-controller)
4. [Authentication](#authentication)
5. [Validation](#validation)
6. [Performance](#performance)
7. [Security](#security)
8. [Monitoring](#monitoring)

---

## 🏗️ Architecture

```
┌──────────────────────────────────────┐
│     Mobile App (NativePHP)           │
│  ┌────────────────────────────────┐  │
│  │   Offline Queue (SQLite)       │  │
│  └───────────┬────────────────────┘  │
│              │ HTTPS                  │
└──────────────┼────────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│     Laravel API Backend              │
│  ┌────────────────────────────────┐  │
│  │   SyncController               │  │
│  │   - POST /api/sync/push        │  │
│  │   - GET  /api/sync/pull/{res}  │  │
│  │   - GET  /api/sync/status      │  │
│  │   - GET  /api/sync/ping        │  │
│  └───────────┬────────────────────┘  │
│              │                        │
│  ┌───────────▼────────────────────┐  │
│  │   Database (MySQL/PostgreSQL)  │  │
│  │   - tasks                      │  │
│  │   - users                      │  │
│  │   - ...                        │  │
│  └────────────────────────────────┘  │
└──────────────────────────────────────┘
```

---

## 🚀 Initial Setup

### 1. Install dependencies

```bash
# Laravel Sanctum for authentication
composer require laravel/sanctum

# Optional: Laravel Telescope for debugging
composer require laravel/telescope --dev

# Publish configs
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 2. CORS configuration

If your mobile app makes requests from a different domain:

**config/cors.php:**

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'], // In production, specify domains

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
```

### 3. Sanctum configuration

**config/sanctum.php:**

```php
'expiration' => null, // Tokens never expire (mobile)

'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

'middleware' => [
    'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
    'encrypt_cookies'      => App\Http\Middleware\EncryptCookies::class,
    'validate_csrf_token'  => App\Http\Middleware\VerifyCsrfToken::class,
],
```

---

## 🎮 Sync Controller

### 1. Create the controller

```bash
php artisan make:controller Api/SyncController
```

### 2. Full controller

**app/Http/Controllers/Api/SyncController.php:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncController extends Controller
{
    /**
     * Push: receive changes from the client
     *
     * POST /api/sync/push
     */
    public function push(Request $request)
    {
        $validated = $request->validate([
            'items'                => 'required|array',
            'items.*.resource'     => 'required|string',
            'items.*.resource_id'  => 'nullable|string',
            'items.*.operation'    => 'required|in:create,update,delete',
            'items.*.data'         => 'required|array',
            'items.*.timestamp'    => 'required|date',
        ]);

        $synced    = [];
        $failed    = [];
        $conflicts = [];

        DB::beginTransaction();

        try {
            foreach ($validated['items'] as $item) {
                try {
                    $result = $this->applyChange($item, $request->user());

                    if (isset($result['conflict'])) {
                        $conflicts[] = $result['conflict'];
                    } else {
                        $synced[] = $item;
                    }
                } catch (\Exception $e) {
                    $failed[] = [
                        'item'  => $item,
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Sync item failed', [
                        'item'  => $item,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success'   => empty($failed),
                'synced'    => count($synced),
                'failed'    => count($failed),
                'conflicts' => $conflicts,
                'errors'    => $failed,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Sync push failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Pull: send changes to the client
     *
     * GET /api/sync/pull/{resource}
     */
    public function pull(Request $request, string $resource)
    {
        $validated = $request->validate([
            'since' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $since = $validated['since'] ?? now()->subDays(30);
        $limit = $validated['limit'] ?? 100;

        // Resolve model class
        $modelClass = $this->getModelClass($resource);

        if (!$modelClass) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        // Filter by user if model has a user_id column
        $query = $modelClass::where('updated_at', '>', $since);

        if ($this->modelBelongsToUser($modelClass)) {
            $query->where('user_id', $request->user()->id);
        }

        $items = $query->limit($limit)
            ->get()
            ->map(fn($model) => [
                'id'        => $model->getKey(),
                'operation' => 'update',
                'data'      => $model->toArray(),
                'timestamp' => $model->updated_at->toIso8601String(),
            ]);

        return response()->json([
            'data'  => $items,
            'count' => $items->count(),
            'since' => $since,
        ]);
    }

    /**
     * Status: server state info
     *
     * GET /api/sync/status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'server_time'         => now()->toIso8601String(),
            'user_id'             => $user->id,
            'available_resources' => $this->getAvailableResources(),
        ]);
    }

    /**
     * Ping: check connectivity
     *
     * GET /api/sync/ping
     */
    public function ping()
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Apply an individual change
     */
    protected function applyChange(array $item, $user): array
    {
        $modelClass = $this->getModelClass($item['resource']);

        if (!$modelClass) {
            throw new \Exception("Resource {$item['resource']} not found");
        }

        $clientTimestamp = Carbon::parse($item['timestamp']);

        // Check for conflict on update/delete
        if (in_array($item['operation'], ['update', 'delete']) && $item['resource_id']) {
            $existing = $modelClass::find($item['resource_id']);

            // Verify resource belongs to user
            if ($existing && $this->modelBelongsToUser($modelClass)) {
                if ($existing->user_id !== $user->id) {
                    throw new \Exception('Unauthorized access to resource');
                }
            }

            if ($existing && $existing->updated_at > $clientTimestamp) {
                return [
                    'conflict' => [
                        'resource'         => $item['resource'],
                        'resource_id'      => $item['resource_id'],
                        'local_data'       => $item['data'],
                        'remote_data'      => $existing->toArray(),
                        'local_timestamp'  => $item['timestamp'],
                        'remote_timestamp' => $existing->updated_at->toIso8601String(),
                    ]
                ];
            }
        }

        // Inject user_id if required
        if ($this->modelBelongsToUser($modelClass)) {
            $item['data']['user_id'] = $user->id;
        }

        // Apply the operation
        match($item['operation']) {
            'create' => $modelClass::create($item['data']),
            'update' => $modelClass::updateOrCreate(
                ['id' => $item['resource_id']],
                $item['data']
            ),
            'delete' => $modelClass::destroy($item['resource_id']),
        };

        return ['success' => true];
    }

    /**
     * Resolve model class from resource name
     */
    protected function getModelClass(string $resource): ?string
    {
        $mapping = config('sync.resource_mapping', []);
        return $mapping[$resource] ?? null;
    }

    /**
     * List available resources
     */
    protected function getAvailableResources(): array
    {
        return array_keys(config('sync.resource_mapping', []));
    }

    /**
     * Check if model belongs to a user
     */
    protected function modelBelongsToUser(string $modelClass): bool
    {
        $instance = new $modelClass;
        return in_array('user_id', $instance->getFillable());
    }
}
```

---

## 🔐 Authentication

### 1. Protected routes

**routes/api.php:**

```php
use App\Http\Controllers\Api\SyncController;

Route::middleware('auth:sanctum')->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push']);
    Route::get('/pull/{resource}', [SyncController::class, 'pull']);
    Route::get('/status', [SyncController::class, 'status']);
    Route::get('/ping', [SyncController::class, 'ping']);
});
```

### 2. Token generation

**app/Http/Controllers/Auth/LoginController.php:**

```php
public function login(Request $request)
{
    $credentials = $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    $user = Auth::user();

    // Create a token for the mobile app
    $token = $user->createToken('mobile-app')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user'  => $user,
    ]);
}
```

### 3. Token revocation

```php
// Revoke all tokens for a user
$user->tokens()->delete();

// Revoke a specific token
$user->tokens()->where('id', $tokenId)->delete();
```

---

## ✅ Validation

### 1. Custom request

```bash
php artisan make:request SyncPushRequest
```

**app/Http/Requests/SyncPushRequest.php:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                  => 'required|array|max:100', // Max 100 items per batch
            'items.*.resource'       => 'required|string|in:tasks,users,projects',
            'items.*.resource_id'    => 'nullable|uuid',
            'items.*.operation'      => 'required|in:create,update,delete',
            'items.*.data'           => 'required|array',
            'items.*.data.title'     => 'sometimes|string|max:255',
            'items.*.timestamp'      => 'required|date|before_or_equal:now',
        ];
    }

    public function messages(): array
    {
        return [
            'items.max'                        => 'Too many items. Maximum 100 per request.',
            'items.*.timestamp.before_or_equal' => 'Timestamp cannot be in the future.',
        ];
    }
}
```

---

## ⚡ Performance

### 1. Caching

```php
use Illuminate\Support\Facades\Cache;

public function pull(Request $request, string $resource)
{
    $cacheKey = "sync_pull_{$resource}_{$request->user()->id}_{$since}";

    return Cache::remember($cacheKey, 300, function () use ($resource, $since) {
        // ... normal query
    });
}
```

### 2. Database indexing

**Migration:**

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->index(['user_id', 'updated_at']);
    $table->index('updated_at');
});
```

### 3. Eager loading

```php
$items = $modelClass::with(['user', 'category'])
    ->where('updated_at', '>', $since)
    ->limit($limit)
    ->get();
```

### 4. Chunk processing

For large amounts of data:

```php
$modelClass::where('updated_at', '>', $since)
    ->chunk(100, function ($items) {
        // Process in batches of 100
    });
```

---

## 🔒 Security

### 1. Rate Limiting

**app/Providers/RouteServiceProvider.php:**

```php
protected function configureRateLimiting()
{
    RateLimiter::for('sync', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });
}
```

**routes/api.php:**

```php
Route::middleware(['auth:sanctum', 'throttle:sync'])->prefix('sync')->group(function () {
    // ...
});
```

### 2. Permission validation

```php
protected function applyChange(array $item, $user): array
{
    // Check the user is allowed to modify this resource
    if (!$user->can('update', $item['resource'])) {
        throw new \Exception('Unauthorized');
    }

    // ...
}
```

### 3. Sanitization

```php
use Illuminate\Support\Str;

$item['data']['title'] = Str::limit(strip_tags($item['data']['title']), 255);
```

---

## 📊 Monitoring

### 1. Structured logs

```php
Log::channel('sync')->info('Sync completed', [
    'user_id'      => $user->id,
    'items_synced' => count($synced),
    'items_failed' => count($failed),
    'conflicts'    => count($conflicts),
    'duration_ms'  => microtime(true) - $startTime,
]);
```

### 2. Metrics

```php
use Illuminate\Support\Facades\Redis;

Redis::hincrby('sync:stats', 'total_syncs', 1);
Redis::hincrby('sync:stats', 'items_synced', count($synced));
```

### 3. Alerts

```php
if (count($failed) > 10) {
    Mail::to('admin@example.com')->send(new SyncFailureAlert($failed));
}
```

---

## 🧪 Tests

### Controller test

```php
public function test_push_sync_creates_items()
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/sync/push', [
        'items' => [
            [
                'resource'    => 'tasks',
                'resource_id' => null,
                'operation'   => 'create',
                'data'        => ['title' => 'New Task'],
                'timestamp'   => now()->toIso8601String(),
            ],
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'synced'  => 1,
    ]);

    $this->assertDatabaseHas('tasks', [
        'title'   => 'New Task',
        'user_id' => $user->id,
    ]);
}
```

---

## 📝 Custom configuration

**config/sync.php:**

```php
return [
    'resource_mapping' => [
        'tasks' => \App\Models\Task::class,
        'users' => \App\Models\User::class,
    ],

    'max_batch_size' => 100,

    'pull_limit' => 1000,

    'pull_days_back' => 30,

    'enable_caching' => true,

    'cache_ttl' => 300, // 5 minutes
];
```

---

## 🎯 Best Practices

1. ✅ **Always use transactions** for multi-item operations
2. ✅ **Log all failures** for debugging
3. ✅ **Validate inputs strictly** on every request
4. ✅ **Limit batch size** (max 100 items)
5. ✅ **Use HTTPS** in production
6. ✅ **Implement rate limiting** to prevent abuse
7. ✅ **Index columns** `updated_at` and `user_id`
8. ✅ **Test with realistic data volumes**

---

## 📞 Support

- 📧 Email: offlinessync@techparse.fr
- 📖 Documentation: https://docs.offlinesync.techparse.fr

---

**Backend configured successfully!** 🎉
