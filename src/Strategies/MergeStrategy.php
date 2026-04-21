<?php

namespace Techparse\OfflineSync\Strategies;

use Techparse\OfflineSync\Contracts\SyncStrategy;

class MergeStrategy implements SyncStrategy
{
    public function resolve(array $conflict): array
    {
        $local = $conflict['local_data'];
        $remote = $conflict['remote_data'];

        // Remote is base; local overrides only when remote value is null/falsy.
        // This means remote "truthy" values always win, while local fills in blanks.
        $merged = array_merge($local, array_filter($remote));

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
