<?php

namespace Techparse\OfflineSync\Events;

use Techparse\OfflineSync\Models\SyncQueueItem;

class ItemQueued
{
    public function __construct(
        public SyncQueueItem $item,
    ) {}
}
