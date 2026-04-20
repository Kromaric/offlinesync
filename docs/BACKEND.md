# Guide Backend - Configuration API Laravel

Guide complet pour configurer le backend Laravel qui gère la synchronisation avec les apps mobiles.

---

## 📋 Table des matières

1. [Architecture](#architecture)
2. [Setup initial](#setup-initial)
3. [Controller de synchronisation](#controller-de-synchronisation)
4. [Authentification](#authentification)
5. [Validation](#validation)
6. [Performance](#performance)
7. [Sécurité](#sécurité)
8. [Monitoring](#monitoring)

---

## 🏗️ Architecture

```
┌──────────────────────────────────────┐
│     App Mobile (NativePHP)          │
│  ┌────────────────────────────────┐ │
│  │   Offline Queue (SQLite)       │ │
│  └───────────┬────────────────────┘ │
│              │ HTTPS                 │
└──────────────┼───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│     API Laravel Backend              │
│  ┌────────────────────────────────┐ │
│  │   SyncController               │ │
│  │   - POST /api/sync/push        │ │
│  │   - GET  /api/sync/pull/{res}  │ │
│  │   - GET  /api/sync/status      │ │
│  │   - GET  /api/sync/ping        │ │
│  └───────────┬────────────────────┘ │
│              │                       │
│  ┌───────────▼────────────────────┐ │
│  │   Database (MySQL/PostgreSQL)  │ │
│  │   - tasks                      │ │
│  │   - users                      │ │
│  │   - ...                        │ │
│  └────────────────────────────────┘ │
└──────────────────────────────────────┘
```

---

## 🚀 Setup Initial

### 1. Installation des dépendances

```bash
# Laravel Sanctum pour l'authentification
composer require laravel/sanctum

# Optionnel : Laravel Telescope pour debug
composer require laravel/telescope --dev

# Publier les configs
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 2. Configuration CORS

Si votre app mobile fait des requêtes depuis un domaine différent :

**config/cors.php :**

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
    'allowed_methods' => ['*'],
    
    'allowed_origins' => ['*'], // En production, spécifier les domaines
    
    'allowed_headers' => ['*'],
    
    'exposed_headers' => [],
    
    'max_age' => 0,
    
    'supports_credentials' => true,
];
```

### 3. Configuration de Sanctum

**config/sanctum.php :**

```php
'expiration' => null, // Tokens n'expirent jamais (mobile)

'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

'middleware' => [
    'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
    'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    'validate_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
],
```

---

## 🎮 Controller de Synchronisation

### 1. Créer le Controller

```bash
php artisan make:controller Api/SyncController
```

### 2. Controller complet

**app/Http/Controllers/Api/SyncController.php :**

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
     * Push : Recevoir les changements du client
     * 
     * POST /api/sync/push
     */
    public function push(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.resource' => 'required|string',
            'items.*.resource_id' => 'nullable|string',
            'items.*.operation' => 'required|in:create,update,delete',
            'items.*.data' => 'required|array',
            'items.*.timestamp' => 'required|date',
        ]);

        $synced = [];
        $failed = [];
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
                        'item' => $item,
                        'error' => $e->getMessage(),
                    ];
                    
                    Log::error('Sync item failed', [
                        'item' => $item,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => empty($failed),
                'synced' => count($synced),
                'failed' => count($failed),
                'conflicts' => $conflicts,
                'errors' => $failed,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Sync push failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Pull : Envoyer les changements au client
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

        // Déterminer la classe du modèle
        $modelClass = $this->getModelClass($resource);
        
        if (!$modelClass) {
            return response()->json([
                'error' => 'Resource not found',
            ], 404);
        }

        // Filtrer par utilisateur si le modèle a une colonne user_id
        $query = $modelClass::where('updated_at', '>', $since);
        
        if ($this->modelBelongsToUser($modelClass)) {
            $query->where('user_id', $request->user()->id);
        }

        $items = $query->limit($limit)
            ->get()
            ->map(fn($model) => [
                'id' => $model->getKey(),
                'operation' => 'update',
                'data' => $model->toArray(),
                'timestamp' => $model->updated_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => $items,
            'count' => $items->count(),
            'since' => $since,
        ]);
    }

    /**
     * Status : Info sur l'état du serveur
     * 
     * GET /api/sync/status
     */
    public function status(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'server_time' => now()->toIso8601String(),
            'user_id' => $user->id,
            'available_resources' => $this->getAvailableResources(),
        ]);
    }

    /**
     * Ping : Vérifier la connexion
     * 
     * GET /api/sync/ping
     */
    public function ping()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Appliquer un changement individuel
     */
    protected function applyChange(array $item, $user): array
    {
        $modelClass = $this->getModelClass($item['resource']);
        
        if (!$modelClass) {
            throw new \Exception("Resource {$item['resource']} not found");
        }

        $clientTimestamp = Carbon::parse($item['timestamp']);

        // Vérifier conflit pour update/delete
        if (in_array($item['operation'], ['update', 'delete']) && $item['resource_id']) {
            $existing = $modelClass::find($item['resource_id']);
            
            // Vérifier que la ressource appartient à l'utilisateur
            if ($existing && $this->modelBelongsToUser($modelClass)) {
                if ($existing->user_id !== $user->id) {
                    throw new \Exception('Unauthorized access to resource');
                }
            }
            
            if ($existing && $existing->updated_at > $clientTimestamp) {
                return [
                    'conflict' => [
                        'resource' => $item['resource'],
                        'resource_id' => $item['resource_id'],
                        'local_data' => $item['data'],
                        'remote_data' => $existing->toArray(),
                        'local_timestamp' => $item['timestamp'],
                        'remote_timestamp' => $existing->updated_at->toIso8601String(),
                    ]
                ];
            }
        }

        // Ajouter user_id si nécessaire
        if ($this->modelBelongsToUser($modelClass)) {
            $item['data']['user_id'] = $user->id;
        }

        // Appliquer l'opération
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
     * Obtenir la classe du modèle depuis le nom de ressource
     */
    protected function getModelClass(string $resource): ?string
    {
        $mapping = config('sync.resource_mapping', []);
        return $mapping[$resource] ?? null;
    }

    /**
     * Liste des ressources disponibles
     */
    protected function getAvailableResources(): array
    {
        return array_keys(config('sync.resource_mapping', []));
    }

    /**
     * Vérifier si le modèle appartient à un utilisateur
     */
    protected function modelBelongsToUser(string $modelClass): bool
    {
        $instance = new $modelClass;
        return in_array('user_id', $instance->getFillable());
    }
}
```

---

## 🔐 Authentification

### 1. Routes protégées

**routes/api.php :**

```php
use App\Http\Controllers\Api\SyncController;

Route::middleware('auth:sanctum')->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push']);
    Route::get('/pull/{resource}', [SyncController::class, 'pull']);
    Route::get('/status', [SyncController::class, 'status']);
    Route::get('/ping', [SyncController::class, 'ping']);
});
```

### 2. Génération de tokens

**app/Http/Controllers/Auth/LoginController.php :**

```php
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json([
            'error' => 'Invalid credentials',
        ], 401);
    }

    $user = Auth::user();
    
    // Créer un token pour l'app mobile
    $token = $user->createToken('mobile-app')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user,
    ]);
}
```

### 3. Révocation de tokens

```php
// Révoquer tous les tokens d'un utilisateur
$user->tokens()->delete();

// Révoquer un token spécifique
$user->tokens()->where('id', $tokenId)->delete();
```

---

## ✅ Validation

### 1. Request personnalisé

```bash
php artisan make:request SyncPushRequest
```

**app/Http/Requests/SyncPushRequest.php :**

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
            'items' => 'required|array|max:100', // Max 100 items par batch
            'items.*.resource' => 'required|string|in:tasks,users,projects',
            'items.*.resource_id' => 'nullable|uuid',
            'items.*.operation' => 'required|in:create,update,delete',
            'items.*.data' => 'required|array',
            'items.*.data.title' => 'sometimes|string|max:255',
            'items.*.timestamp' => 'required|date|before_or_equal:now',
        ];
    }

    public function messages(): array
    {
        return [
            'items.max' => 'Trop d\'items. Maximum 100 par requête.',
            'items.*.timestamp.before_or_equal' => 'Timestamp ne peut pas être dans le futur.',
        ];
    }
}
```

---

## ⚡ Performance

### 1. Mise en cache

```php
use Illuminate\Support\Facades\Cache;

public function pull(Request $request, string $resource)
{
    $cacheKey = "sync_pull_{$resource}_{$request->user()->id}_{$since}";
    
    return Cache::remember($cacheKey, 300, function () use ($resource, $since) {
        // ... requête normale
    });
}
```

### 2. Indexation de la base de données

**Migration :**

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->index(['user_id', 'updated_at']);
    $table->index('updated_at');
});
```

### 3. Eager Loading

```php
$items = $modelClass::with(['user', 'category'])
    ->where('updated_at', '>', $since)
    ->limit($limit)
    ->get();
```

### 4. Chunk processing

Pour de grandes quantités de données :

```php
$modelClass::where('updated_at', '>', $since)
    ->chunk(100, function ($items) {
        // Traiter par lots de 100
    });
```

---

## 🔒 Sécurité

### 1. Rate Limiting

**app/Providers/RouteServiceProvider.php :**

```php
protected function configureRateLimiting()
{
    RateLimiter::for('sync', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });
}
```

**routes/api.php :**

```php
Route::middleware(['auth:sanctum', 'throttle:sync'])->prefix('sync')->group(function () {
    // ...
});
```

### 2. Validation des permissions

```php
protected function applyChange(array $item, $user): array
{
    // Vérifier que l'utilisateur a le droit de modifier cette ressource
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

### 1. Logs structurés

```php
Log::channel('sync')->info('Sync completed', [
    'user_id' => $user->id,
    'items_synced' => count($synced),
    'items_failed' => count($failed),
    'conflicts' => count($conflicts),
    'duration_ms' => microtime(true) - $startTime,
]);
```

### 2. Métriques

```php
use Illuminate\Support\Facades\Redis;

Redis::hincrby('sync:stats', 'total_syncs', 1);
Redis::hincrby('sync:stats', 'items_synced', count($synced));
```

### 3. Alertes

```php
if (count($failed) > 10) {
    Mail::to('admin@example.com')->send(new SyncFailureAlert($failed));
}
```

---

## 🧪 Tests

### Test du controller

```php
public function test_push_sync_creates_items()
{
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->postJson('/api/sync/push', [
        'items' => [
            [
                'resource' => 'tasks',
                'resource_id' => null,
                'operation' => 'create',
                'data' => ['title' => 'New Task'],
                'timestamp' => now()->toIso8601String(),
            ],
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'synced' => 1,
    ]);

    $this->assertDatabaseHas('tasks', [
        'title' => 'New Task',
        'user_id' => $user->id,
    ]);
}
```

---

## 📝 Configuration personnalisée

**config/sync.php :**

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

## 🎯 Bonnes Pratiques

1. ✅ **Toujours utiliser des transactions** pour les opérations multiples
2. ✅ **Logger tous les échecs** pour debugging
3. ✅ **Valider strictement** les données entrantes
4. ✅ **Limiter la taille des batches** (max 100 items)
5. ✅ **Utiliser HTTPS** en production
6. ✅ **Implémenter rate limiting** pour éviter les abus
7. ✅ **Indexer les colonnes** updated_at et user_id
8. ✅ **Tester avec de vraies données** volumineuses

---

## 📞 Support

- 📧 Email : support@techparse.fr
- 📖 Documentation : https://docs.techparse.fr/offline-sync

---

**Backend configuré avec succès !** 🎉
