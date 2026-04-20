<?php

namespace Techparse\OfflineSync\Traits;

use Techparse\OfflineSync\Facades\OfflineSync;

trait Syncable
{
    /**
     * Flag to prevent sync loops
     */
    protected bool $fromSync = false;

    /**
     * Boot the trait
     */
    protected static function bootSyncable(): void
    {
        // Intercept creations
        static::created(function ($model) {
            if (!$model->isFromSync()) {
                OfflineSync::queue($model, 'create');
            }
        });

        // Intercept updates
        static::updated(function ($model) {
            if (!$model->isFromSync() && $model->wasChanged()) {
                OfflineSync::queue($model, 'update');
            }
        });

        // Intercept deletions
        static::deleted(function ($model) {
            if (!$model->isFromSync()) {
                OfflineSync::queue($model, 'delete');
            }
        });
    }

    /**
     * Flag to prevent sync loops
     */
    public function isFromSync(): bool
    {
        return $this->fromSync;
    }

    /**
     * Mark the model as coming from a sync
     */
    public function markAsFromSync(): self
    {
        $this->fromSync = true;
        return $this;
    }

    /**
     * Resource name for sync
     */
    public function getSyncResourceName(): string
    {
        return $this->syncResourceName ?? $this->getTable();
    }

    /**
     * Fields to exclude from sync
     */
    public function getSyncExcluded(): array
    {
        return $this->syncExcluded ?? [];
    }
}
