<?php

namespace Techparse\OfflineSync\Events;

class SyncStarted
{
    public function __construct(
        public array $resources,
        public string $direction, // push, pull, bidirectional
    ) {}
}
