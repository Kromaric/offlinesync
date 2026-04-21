<?php

namespace Techparse\OfflineSync;

use Illuminate\Database\Eloquent\Model;
use Techparse\OfflineSync\Models\SyncQueueItem;
use Techparse\OfflineSync\Events\ItemQueued;
use Techparse\OfflineSync\Events\QueuePurged;

class QueueManager
{
    /**
     * Add an item to the sync queue
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
     * Retrieve pending items from the queue
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
     * Serialize a model's data for the sync payload
     */
    protected function serializeModel(Model $model, string $operation): array
    {
        if ($operation === 'delete') {
            return [
                'id' => $model->getKey(),
                'deleted_at' => now()->toIso8601String(),
            ];
        }

        // Retrieve the relevant attributes
        $data = $operation === 'create'
            ? $model->getAttributes()
            : $model->getDirty();

        // Exclude configured fields (getSyncExcluded() is provided by the Syncable trait)
        $excluded = array_merge(['fromSync'], $model->getSyncExcluded());

        $data = array_diff_key($data, array_flip($excluded));

        // Ensure timestamps are serialized as ISO 8601 strings.
        // Read from getAttributes() so mock expectations are honoured.
        $attrs = $model->getAttributes();
        foreach (['updated_at', 'created_at'] as $tsField) {
            if ($tsField === 'created_at' && $operation !== 'create') {
                continue;
            }
            $raw = $attrs[$tsField] ?? null;
            if ($raw !== null) {
                $data[$tsField] = $raw instanceof \DateTimeInterface
                    ? $raw->format(\DateTime::ATOM)
                    : \Carbon\Carbon::parse($raw)->toIso8601String();
            }
        }

        return $data;
    }

    /**
     * Generate a unique hash for a queue entry
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
