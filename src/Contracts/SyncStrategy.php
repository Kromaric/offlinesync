<?php

namespace Techparse\OfflineSync\Contracts;

interface SyncStrategy
{
    /**
     * Resolve a conflict between local and remote data
     *
     * @param array $conflict Contains: resource, resource_id, local_data, remote_data, local_timestamp, remote_timestamp
     * @return array The final data to apply
     */
    public function resolve(array $conflict): array;

    /**
     * Name of the strategy
     */
    public function name(): string;
}
