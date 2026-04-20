<?php

namespace Techparse\OfflineSync\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Techparse\OfflineSync\Models\SyncQueueItem queue(\Illuminate\Database\Eloquent\Model $model, string $operation)
 * @method static \Illuminate\Support\Collection getPending(?string $resource = null)
 * @method static array sync(?array $resources = null)
 * @method static array push(?array $resources = null)
 * @method static array pull(array $resources)
 * @method static array getStatus()
 * @method static int purgeOldItems(int $days = 7)
 *
 * @see \Techparse\OfflineSync\QueueManager
 * @see \Techparse\OfflineSync\SyncEngine
 */
class OfflineSync extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'offline-sync';
    }
}
