<?php

namespace Techparse\OfflineSync\Events;

class SyncCompleted
{
    public function __construct(
        public int $synced,
        public int $failed,
        public int $conflicts,
        public int $durationMs,
    ) {}
}
