<?php

namespace Techparse\OfflineSync\Strategies;

use Techparse\OfflineSync\Contracts\SyncStrategy;

class ClientWinsStrategy implements SyncStrategy
{
    public function resolve(array $conflict): array
    {
        return [
            'data' => $conflict['local_data'],
            'winner' => 'client',
            'action' => 'force_push',
        ];
    }

    public function name(): string
    {
        return 'client_wins';
    }
}
