<?php

namespace Techparse\OfflineSync;

use Techparse\OfflineSync\Contracts\SyncStrategy;
use Techparse\OfflineSync\Events\ConflictDetected;

class ConflictResolver
{
    /**
     * Resolve a conflict
     */
    public function resolve(array $conflict): array
    {
        event(new ConflictDetected($conflict));

        // Read config at resolve-time so runtime config() changes are picked up
        $perResource  = config('offline-sync.conflict_resolution.per_resource', []);
        $default      = config('offline-sync.conflict_resolution.default_strategy', 'server_wins');
        $strategyName = $perResource[$conflict['resource'] ?? ''] ?? $default;
        $strategy     = $this->getStrategy($strategyName);

        return $strategy->resolve($conflict);
    }

    /**
     * Get a strategy instance
     */
    protected function getStrategy(string $name): SyncStrategy
    {
        return match($name) {
            'server_wins' => app(\Techparse\OfflineSync\Strategies\ServerWinsStrategy::class),
            'client_wins' => app(\Techparse\OfflineSync\Strategies\ClientWinsStrategy::class),
            'last_write_wins' => app(\Techparse\OfflineSync\Strategies\LastWriteWinsStrategy::class),
            'merge' => app(\Techparse\OfflineSync\Strategies\MergeStrategy::class),
            default => app(\Techparse\OfflineSync\Strategies\ServerWinsStrategy::class),
        };
    }
}
