<?php

namespace Techparse\OfflineSync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Techparse\OfflineSync\Events\SyncStarted;
use Techparse\OfflineSync\Events\SyncCompleted;
use Techparse\OfflineSync\Events\SyncFailed;
use Techparse\OfflineSync\Events\ItemSynced;
use Techparse\OfflineSync\Models\SyncLog;

class SyncEngine
{
    protected ConflictResolver $conflictResolver;
    protected ConnectivityService $connectivity;

    public function __construct(
        ConflictResolver $conflictResolver,
        ConnectivityService $connectivity
    ) {
        $this->conflictResolver = $conflictResolver;
        $this->connectivity = $connectivity;
    }

    /**
     * Synchronisation bidirectionnelle
     */
    public function sync(?array $resources = null): array
    {
        event(new SyncStarted($resources ?? [], 'bidirectional'));
        $startTime = microtime(true);

        try {
            $pushResult = $this->push($resources);
            $pullResult = $this->pull($resources ?? []);

            $result = [
                'synced' => $pushResult['synced'] + $pullResult['synced'],
                'failed' => $pushResult['failed'] + $pullResult['failed'],
                'conflicts' => $pushResult['conflicts'] ?? 0,
            ];

            $duration = (int)((microtime(true) - $startTime) * 1000);
            event(new SyncCompleted(
                $result['synced'],
                $result['failed'],
                $result['conflicts'],
                $duration
            ));

            $this->logSync('bidirectional', $result, $duration);

            return $result;
        } catch (\Exception $e) {
            event(new SyncFailed($e->getMessage(), $e));
            throw $e;
        }
    }

    /**
     * Push : envoyer les changements locaux vers le serveur
     */
    public function push(?array $resources = null): array
    {
        if (!$this->connectivity->isOnline()) {
            throw new \Exception('No internet connection');
        }

        $queueManager = app(QueueManager::class);
        $pending = $resources 
            ? collect($resources)->flatMap(fn($r) => $queueManager->getPending($r))
            : $queueManager->getPending();

        if ($pending->isEmpty()) {
            return ['synced' => 0, 'failed' => 0];
        }

        $batchSize = config('offline-sync.performance.batch_size', 50);
        $batches = $pending->chunk($batchSize);

        $synced = 0;
        $failed = 0;
        $conflicts = [];

        foreach ($batches as $batch) {
            $items = $batch->map(fn($item) => [
                'resource' => $item->resource,
                'resource_id' => $item->resource_id,
                'operation' => $item->operation,
                'data' => $item->payload,
                'timestamp' => $item->created_at->toIso8601String(),
            ])->toArray();

            try {
                $response = $this->secureRequest('post', $this->getApiUrl('/sync/push'), [
                    'items' => $items,
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $synced += $result['synced'] ?? 0;
                    $failed += $result['failed'] ?? 0;

                    if (isset($result['conflicts'])) {
                        $conflicts = array_merge($conflicts, $result['conflicts']);
                    }

                    // Marquer les items comme synchronisés
                    foreach ($batch as $item) {
                        $item->markAsSynced();
                        event(new ItemSynced($item));
                    }
                } else {
                    $failed += $batch->count();
                    foreach ($batch as $item) {
                        $item->markAsFailed('HTTP error: ' . $response->status());
                    }
                }
            } catch (\Exception $e) {
                $failed += $batch->count();
                foreach ($batch as $item) {
                    $item->markAsFailed($e->getMessage());
                }
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Pull : récupérer les changements du serveur
     */
    public function pull(array $resources): array
    {
        if (!$this->connectivity->isOnline()) {
            throw new \Exception('No internet connection');
        }

        $synced = 0;

        foreach ($resources as $resource) {
            try {
                $response = $this->secureRequest('get', $this->getApiUrl("/sync/pull/{$resource}"), [
                    'since' => $this->getLastPullTimestamp($resource),
                    'limit' => config('offline-sync.performance.batch_size', 100),
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $items = $data['data'] ?? [];

                    foreach ($items as $item) {
                        $this->applyRemoteChange($resource, $item);
                        $synced++;
                    }

                    $this->updateLastPullTimestamp($resource);
                }
            } catch (\Exception $e) {
                // Log error but continue with other resources
                continue;
            }
        }

        return ['synced' => $synced, 'failed' => 0];
    }

    /**
     * Obtenir le statut de synchronisation
     */
    public function getStatus(): array
    {
        $queueManager = app(QueueManager::class);
        $pending = $queueManager->getPending();

        $lastSync = SyncLog::orderBy('synced_at', 'desc')->first();

        return [
            'pending_count' => $pending->count(),
            'last_sync' => $lastSync?->synced_at?->toIso8601String(),
            'is_syncing' => false, // TODO: implement sync lock
            'is_online' => $this->connectivity->isOnline(),
        ];
    }

    /**
     * Appliquer un changement distant localement
     */
    protected function applyRemoteChange(string $resource, array $item): void
    {
        $modelClass = $this->getModelClass($resource);
        if (!$modelClass) {
            return;
        }

        $model = new $modelClass;
        
        // Marquer comme venant de la sync pour éviter les boucles
        if (method_exists($model, 'markAsFromSync')) {
            $model->markAsFromSync();
        }

        match($item['operation']) {
            'create', 'update' => $modelClass::updateOrCreate(
                ['id' => $item['data']['id']],
                $item['data']
            ),
            'delete' => $modelClass::destroy($item['data']['id']),
            default => null,
        };
    }

    /**
     * Logger une synchronisation
     */
    protected function logSync(string $direction, array $result, int $durationMs): void
    {
        if (!config('offline-sync.logging.enabled', true)) {
            return;
        }

        SyncLog::create([
            'synced_at' => now(),
            'direction' => $direction,
            'items_count' => $result['synced'] + $result['failed'],
            'synced_count' => $result['synced'],
            'failed_count' => $result['failed'],
            'conflicts_count' => $result['conflicts'] ?? 0,
            'duration_ms' => $durationMs,
            'success' => $result['failed'] === 0,
        ]);
    }

    /**
     * Obtenir les headers d'authentification
     */
    protected function getAuthHeaders(): array
    {
        $method = config('offline-sync.security.auth_method', 'bearer');
        
        return match($method) {
            'bearer' => $this->getBearerAuth(),
            'api_key' => $this->getApiKeyAuth(),
            default => [],
        };
    }

    /**
     * Auth Bearer Token
     */
    protected function getBearerAuth(): array
    {
        $token = $this->getStoredToken() ?? config('offline-sync.security.api_token');
        
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Auth API Key
     */
    protected function getApiKeyAuth(): array
    {
        return [
            'X-API-Key' => config('offline-sync.security.api_token'),
            'Accept' => 'application/json',
        ];
    }

    /**
     * Récupérer le token stocké
     */
    protected function getStoredToken(): ?string
    {
        $path = storage_path('app/sync_token.enc');
        
        if (!file_exists($path)) {
            return null;
        }
        
        $content = file_get_contents($path);
        
        return config('offline-sync.security.token_storage') === 'encrypted'
            ? Crypt::decryptString($content)
            : $content;
    }

    /**
     * Faire une requête HTTP sécurisée
     */
    protected function secureRequest(string $method, string $url, array $data = []): \Illuminate\Http\Client\Response
    {
        if (config('offline-sync.security.require_https', true) && !str_starts_with($url, 'https://')) {
            throw new \Exception('HTTPS is required for sync operations');
        }

        return Http::timeout(config('offline-sync.connectivity.timeout', 30))
            ->withHeaders($this->getAuthHeaders())
            ->$method($url, $data);
    }

    /**
     * Obtenir l'URL de l'API
     */
    protected function getApiUrl(string $path): string
    {
        return rtrim(config('offline-sync.api_url'), '/') . $path;
    }

    /**
     * Obtenir la classe du modèle
     */
    protected function getModelClass(string $resource): ?string
    {
        $mapping = config('offline-sync.resource_mapping', []);
        return $mapping[$resource] ?? null;
    }

    /**
     * Obtenir le timestamp du dernier pull
     */
    protected function getLastPullTimestamp(string $resource): string
    {
        $log = SyncLog::where('direction', 'pull')
            ->where('details->resource', $resource)
            ->orderBy('synced_at', 'desc')
            ->first();

        return $log?->synced_at?->toIso8601String() ?? now()->subDays(30)->toIso8601String();
    }

    /**
     * Mettre à jour le timestamp du dernier pull
     */
    protected function updateLastPullTimestamp(string $resource): void
    {
        SyncLog::create([
            'synced_at' => now(),
            'direction' => 'pull',
            'items_count' => 0,
            'synced_count' => 0,
            'failed_count' => 0,
            'conflicts_count' => 0,
            'duration_ms' => 0,
            'success' => true,
            'details' => ['resource' => $resource],
        ]);
    }
}
