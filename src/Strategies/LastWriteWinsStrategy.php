<?php

namespace Techparse\OfflineSync\Strategies;

use Techparse\OfflineSync\Contracts\SyncStrategy;
use Carbon\Carbon;

class LastWriteWinsStrategy implements SyncStrategy
{
    public function resolve(array $conflict): array
    {
        $localTime = Carbon::parse($conflict['local_timestamp']);
        $remoteTime = Carbon::parse($conflict['remote_timestamp']);

        if ($remoteTime->greaterThan($localTime)) {
            return [
                'data' => $conflict['remote_data'],
                'winner' => 'server',
                'action' => 'overwrite_local',
                'reason' => 'remote_newer',
            ];
        }

        return [
            'data' => $conflict['local_data'],
            'winner' => 'client',
            'action' => 'force_push',
            'reason' => 'local_newer',
        ];
    }

    public function name(): string
    {
        return 'last_write_wins';
    }
}
