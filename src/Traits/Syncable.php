<?php

namespace Techparse\OfflineSync\Traits;

use Techparse\OfflineSync\Facades\OfflineSync;

trait Syncable
{
    /**
     * Flag pour éviter les boucles de sync
     */
    protected bool $fromSync = false;

    /**
     * Boot du trait
     */
    protected static function bootSyncable(): void
    {
        // Intercepter les créations
        static::created(function ($model) {
            if (!$model->isFromSync()) {
                OfflineSync::queue($model, 'create');
            }
        });

        // Intercepter les mises à jour
        static::updated(function ($model) {
            if (!$model->isFromSync() && $model->wasChanged()) {
                OfflineSync::queue($model, 'update');
            }
        });

        // Intercepter les suppressions
        static::deleted(function ($model) {
            if (!$model->isFromSync()) {
                OfflineSync::queue($model, 'delete');
            }
        });
    }

    /**
     * Flag pour éviter les boucles de sync
     */
    public function isFromSync(): bool
    {
        return $this->fromSync;
    }

    /**
     * Marquer le modèle comme venant d'une sync
     */
    public function markAsFromSync(): self
    {
        $this->fromSync = true;
        return $this;
    }

    /**
     * Nom de la ressource pour la sync
     */
    public function getSyncResourceName(): string
    {
        return $this->syncResourceName ?? $this->getTable();
    }

    /**
     * Champs à exclure de la sync
     */
    public function getSyncExcluded(): array
    {
        return $this->syncExcluded ?? [];
    }
}
