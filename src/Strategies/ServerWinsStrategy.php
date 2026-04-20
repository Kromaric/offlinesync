<?php

namespace Techparse\OfflineSync\Strategies;

use Techparse\OfflineSync\Contracts\SyncStrategy;

class ServerWinsStrategy implements SyncStrategy
{
    public function resolve(array $conflict): array
    {
        return [
            'data' => $conflict['remote_data'],
            'winner' => 'server',
            'action' => 'overwrite_local',
        ];
    }

    public function name(): string
    {
        return 'server_wins';
    }
}
