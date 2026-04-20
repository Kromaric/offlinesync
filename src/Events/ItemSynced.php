<?php

namespace Techparse\OfflineSync\Events;

use Techparse\OfflineSync\Models\SyncQueueItem;

class ItemSynced
{
    public function __construct(
        public SyncQueueItem $item,
    ) {}
}
