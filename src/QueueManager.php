<?php

namespace Techparse\OfflineSync;

use Illuminate\Database\Eloquent\Model;
use Techparse\OfflineSync\Models\SyncQueueItem;
use Techparse\OfflineSync\Events\ItemQueued;
use Techparse\OfflineSync\Events\QueuePurged;

class QueueManager
{
    /**
     * Add an item to the queue
     */
    public function queue(Model $model, string $operation): SyncQueueItem
    {
        $resource = $model->getSyncResourceName();
        $payload = $this->serializeModel($model, $operation);
        $hash = $this->generateHash($resource, $model->getKey(), $operation, $payload);

        $item = SyncQueueItem::updateOrCreate(
            ['hash' => $hash],
            [
                'resource' => $resource,
                'resource_id' => $operation === 'create' ? null : $model->getKey(),
                'operation' => $operation,
                'payload' => $payload,
                'status' => 'pending',
                'created_at' => now(),
            ]
        );

        event(new ItemQueued($item));

        return $item;
    }

    /**
     * Retrieve pending items
     */
    public function getPending(?string $resource = null): \Illuminate\Support\Collection
    {
        $query = SyncQueueItem::pending()->orderBy('created_at');

        if ($resource) {
            $query->forResource($resource);
        }

        return $query->get();
    }

    /**
     * Purge old synced items
     */
    public function purgeOldItems(int $days = 7): int
    {
        $count = SyncQueueItem::where('status', 'synced')
            ->where('synced_at', '<', now()->subDays($days))
            ->delete();

        if ($count > 0) {
            event(new QueuePurged($count));
        }

        return $count;
    }

    /**
     * Serialize a model for sync
     */
    protected function serializeModel(Model $model, string $operation): array
    {
        if ($operation === 'delete') {
            return [
                'id' => $model->getKey(),
                'deleted_at' => now()->toIso8601String(),
            ];
        }

        // Retrieve the attributes
        $data = $operation === 'create'
            ? $model->getAttributes()
            : $model->getDirty();

        // Exclude certain fields
        $excluded = array_merge(
            ['fromSync'],
            method_exists($model, 'getSyncExcluded') ? $model->getSyncExcluded() : []
        );

        $data = array_diff_key($data, array_flip($excluded));

        // Add timestamps
        if (isset($model->updated_at)) {
            $data['updated_at'] = $model->updated_at->toIso8601String();
        }
        if ($operation === 'create' && isset($model->created_at)) {
            $data['created_at'] = $model->created_at->toIso8601String();
        }

        return $data;
    }

    /**
     * Generate a unique hash
     */
    protected function generateHash(string $resource, $id, string $operation, array $payload): string
    {
        return md5(
            $resource . 
            ($id ?? 'new') . 
            $operation . 
            json_encode($payload)
        );
    }
}
