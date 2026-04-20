<?php

namespace Techparse\OfflineSync\Events;

class ConflictDetected
{
    public function __construct(
        public array $conflict,
    ) {}
}
