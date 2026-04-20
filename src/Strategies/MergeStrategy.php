<?php

namespace Techparse\OfflineSync\Strategies;

use Techparse\OfflineSync\Contracts\SyncStrategy;

class MergeStrategy implements SyncStrategy
{
    public function resolve(array $conflict): array
    {
        $local = $conflict['local_data'];
        $remote = $conflict['remote_data'];

        // Merge intelligent : prendre les valeurs non-nulles de chaque côté
        $merged = array_merge($remote, array_filter($local, fn($v) => $v !== null));

        return [
            'data' => $merged,
            'winner' => 'merged',
            'action' => 'merge_and_push',
            'conflicts_merged' => array_keys(array_intersect_key($local, $remote)),
        ];
    }

    public function name(): string
    {
        return 'merge';
    }
}
