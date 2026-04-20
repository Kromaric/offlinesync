<?php

namespace Techparse\OfflineSync\Events;

class SyncFailed
{
    public function __construct(
        public string $reason,
        public ?\Throwable $exception = null,
    ) {}
}
