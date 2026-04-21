<?php

namespace Techparse\OfflineSync;

use Illuminate\Support\Facades\Http;
use Techparse\OfflineSync\Events\SyncStarted;
use Techparse\OfflineSync\Events\SyncCompleted;
use Techparse\OfflineSync\Events\SyncFailed;
use Techparse\OfflineSync\Events\ItemSynced;
use Techparse\OfflineSync\Models\SyncLog;

class SyncEngine
{
    protected ConflictResolver $conflictResolver;

    public function __construct(ConflictResolver $conflictResolver)
    {
        $this->conflictResolver = $conflictResolver;
    }

    /**
     * Resolve ConnectivityService from the container so test mocks are honoured.
     */
    protected function connectivity(): ConnectivityService
    {
        return app(ConnectivityService::class);
    }

    /**
     * Bidirectional synchronization (push then pull).
     */
    public function sync(?array $resources = null): array
    {
        event(new SyncStarted($resources ?? [], 'bidirectional'));
        $startTime = microtime(true);

        try {
            $pushResult = $this->push($resources);
            $pullResult = $this->pull($resources ?? []);

            $result = [
                'synced'    => $pushResult['synced'] + $pullResult['synced'],
                'failed'    => $pushResult['failed'] + $pullResult['failed'],
                'conflicts' => $pushResult['conflicts'] ?? 0,
            ];

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            event(new SyncCompleted($result['synced'], $result['failed'], $result['conflicts'], $duration));
            $this->logSync('bidirectional', $result, $duration);

            return $result;
        } catch (\Exception $e) {
            event(new SyncFailed($e->getMessage(), $e));
            throw $e;
        }
    }

    /**
     * Validate that the configured API URL uses HTTPS when required.
     */
    protected function validateApiUrlSecurity(): void
    {
        $apiUrl = config('offline-sync.api_url', '');
        if (config('offline-sync.security.require_https', true) && ! str_starts_with($apiUrl, 'https://')) {
            throw new \Exception('HTTPS is required for sync operations');
        }
    }

    /**
     * Push: send pending local changes to the server.
     */
    public function push(?array $resources = null): array
    {
        if (! $this->connectivity()->isOnline()) {
            throw new \Exception('No internet connection');
        }

        $this->validateApiUrlSecurity();

        $queueManager = app(QueueManager::class);
        $pending = $resources
            ? collect($resources)->flatMap(fn ($r) => $queueManager->getPending($r))
            : $queueManager->getPending();

        if ($pending->isEmpty()) {
            return ['synced' => 0, 'failed' => 0];
        }

        $synced    = 0;
        $failed    = 0;
        $conflicts = [];

        foreach ($pending->chunk(config('offline-sync.performance.batch_size', 50)) as $batch) {
            $items = $batch->map(fn ($item) => [
                'resource'    => $item->resource,
                'resource_id' => $item->resource_id,
                'operation'   => $item->operation,
                'data'        => $item->payload,
                'timestamp'   => $item->created_at->toIso8601String(),
            ])->toArray();

            try {
                $response = $this->request('post', $this->apiUrl('/sync/push'), ['items' => $items]);

                if ($response->successful()) {
                    $result     = $response->json();
                    $synced    += $result['synced'] ?? 0;
                    $failed    += $result['failed'] ?? 0;
                    $conflicts  = array_merge($conflicts, $result['conflicts'] ?? []);

                    foreach ($batch as $item) {
                        $item->markAsSynced();
                        event(new ItemSynced($item));
                    }
                } else {
                    $failed += $batch->count();
                    foreach ($batch as $item) {
                        $item->markAsFailed('HTTP ' . $response->status());
                    }
                }
            } catch (\Exception $e) {
                $failed += $batch->count();
                foreach ($batch as $item) {
                    $item->markAsFailed($e->getMessage());
                }
            }
        }

        return ['synced' => $synced, 'failed' => $failed, 'conflicts' => $conflicts];
    }

    /**
     * Pull: fetch remote changes and apply them locally.
     */
    public function pull(array $resources): array
    {
        if (! $this->connectivity()->isOnline()) {
            throw new \Exception('No internet connection');
        }

        $synced = 0;

        foreach ($resources as $resource) {
            try {
                $response = $this->request('get', $this->apiUrl("/sync/pull/{$resource}"), [
                    'since' => $this->lastPullTimestamp($resource),
                    'limit' => config('offline-sync.performance.batch_size', 100),
                ]);

                if ($response->successful()) {
                    foreach ($response->json('data', []) as $item) {
                        $this->applyRemoteChange($resource, $item);
                        $synced++;
                    }
                    $this->recordPullTimestamp($resource);
                }
            } catch (\Exception) {
                continue; // log error but keep syncing other resources
            }
        }

        return ['synced' => $synced, 'failed' => 0];
    }

    /**
     * Return the current sync status.
     */
    public function getStatus(): array
    {
        $pending = app(QueueManager::class)->getPending();
        $lastSync = SyncLog::orderBy('synced_at', 'desc')->first();

        return [
            'pending_count' => $pending->count(),
            'last_sync'     => $lastSync?->synced_at?->toIso8601String(),
            'is_syncing'    => false,
            'is_online'     => $this->connectivity()->isOnline(),
        ];
    }

    // -------------------------------------------------------------------------
    // HTTP
    // -------------------------------------------------------------------------

    /**
     * Make an HTTP request to the sync API.
     *
     * Authentication is the responsibility of the host application.
     * Extra headers (e.g. a Bearer token) can be added via the
     * `offline-sync.security.headers` config key.
     */
    protected function request(string $method, string $url, array $data = []): \Illuminate\Http\Client\Response
    {
        $headers = array_merge(
            ['Accept' => 'application/json'],
            config('offline-sync.security.headers', [])
        );

        return Http::timeout(config('offline-sync.connectivity.timeout', 30))
            ->withHeaders($headers)
            ->$method($url, $data);
    }

    /**
     * Build an absolute URL for a sync API path.
     */
    protected function apiUrl(string $path): string
    {
        return rtrim(config('offline-sync.api_url'), '/') . $path;
    }

    // -------------------------------------------------------------------------
    // Local data
    // -------------------------------------------------------------------------

    /**
     * Apply a remote change to the local database.
     */
    protected function applyRemoteChange(string $resource, array $item): void
    {
        $modelClass = config('offline-sync.resource_mapping', [])[$resource] ?? null;
        if (! $modelClass) {
            return;
        }

        $model = new $modelClass;
        if (method_exists($model, 'markAsFromSync')) {
            $model->markAsFromSync();
        }

        match ($item['operation']) {
            'create', 'update' => $modelClass::updateOrCreate(['id' => $item['data']['id']], $item['data']),
            'delete'           => $modelClass::destroy($item['data']['id']),
            default            => null,
        };
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    protected function logSync(string $direction, array $result, int $durationMs): void
    {
        if (! config('offline-sync.logging.enabled', true)) {
            return;
        }

        SyncLog::create([
            'synced_at'       => now(),
            'direction'       => $direction,
            'items_count'     => $result['synced'] + $result['failed'],
            'synced_count'    => $result['synced'],
            'failed_count'    => $result['failed'],
            'conflicts_count' => $result['conflicts'] ?? 0,
            'duration_ms'     => $durationMs,
            'success'         => $result['failed'] === 0,
        ]);
    }

    protected function lastPullTimestamp(string $resource): string
    {
        $log = SyncLog::where('direction', 'pull')
            ->where('details->resource', $resource)
            ->orderBy('synced_at', 'desc')
            ->first();

        return $log?->synced_at?->toIso8601String() ?? now()->subDays(30)->toIso8601String();
    }

    protected function recordPullTimestamp(string $resource): void
    {
        SyncLog::create([
            'synced_at'       => now(),
            'direction'       => 'pull',
            'items_count'     => 0,
            'synced_count'    => 0,
            'failed_count'    => 0,
            'conflicts_count' => 0,
            'duration_ms'     => 0,
            'success'         => true,
            'details'         => ['resource' => $resource],
        ]);
    }
}
