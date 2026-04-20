<?php

namespace Techparse\OfflineSync;

use Techparse\OfflineSync\Contracts\SyncStrategy;
use Techparse\OfflineSync\Events\ConflictDetected;

class ConflictResolver
{
    protected array $strategies = [];
    protected string $defaultStrategy;

    public function __construct()
    {
        $this->defaultStrategy = config('offline-sync.conflict_resolution.default_strategy', 'server_wins');
        
        // Charger les stratégies par ressource
        $this->strategies = config('offline-sync.conflict_resolution.per_resource', []);
    }

    /**
     * Résoudre un conflit
     */
    public function resolve(array $conflict): array
    {
        event(new ConflictDetected($conflict));

        $strategyName = $this->strategies[$conflict['resource']] ?? $this->defaultStrategy;
        $strategy = $this->getStrategy($strategyName);

        return $strategy->resolve($conflict);
    }

    /**
     * Obtenir une instance de stratégie
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
