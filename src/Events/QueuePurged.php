<?php

namespace Techparse\OfflineSync\Events;

class QueuePurged
{
    public function __construct(
        public int $itemsDeleted,
    ) {}
}
