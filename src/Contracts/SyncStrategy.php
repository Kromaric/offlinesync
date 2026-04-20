<?php

namespace Techparse\OfflineSync\Contracts;

interface SyncStrategy
{
    /**
     * Résoudre un conflit entre données locales et distantes
     *
     * @param array $conflict Contient: resource, resource_id, local_data, remote_data, local_timestamp, remote_timestamp
     * @return array Les données finales à appliquer
     */
    public function resolve(array $conflict): array;

    /**
     * Nom de la stratégie
     */
    public function name(): string;
}
