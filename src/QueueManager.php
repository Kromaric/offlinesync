<?php

namespace Techparse\OfflineSync;

use Illuminate\Database\Eloquent\Model;
use Techparse\OfflineSync\Models\SyncQueueItem;
use Techparse\OfflineSync\Events\ItemQueued;
use Techparse\OfflineSync\Events\QueuePurged;

class QueueManager
{
    /**
     * Ajouter un item à la queue
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
     * Récupérer les items en attente
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
     * Purger les items synchronisés anciens
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
     * Sérialiser un modèle pour la sync
     */
    protected function serializeModel(Model $model, string $operation): array
    {
        if ($operation === 'delete') {
            return [
                'id' => $model->getKey(),
                'deleted_at' => now()->toIso8601String(),
            ];
        }

        // Récupérer les attributs
        $data = $operation === 'create' 
            ? $model->getAttributes()
            : $model->getDirty();

        // Exclure certains champs
        $excluded = array_merge(
            ['fromSync'],
            method_exists($model, 'getSyncExcluded') ? $model->getSyncExcluded() : []
        );

        $data = array_diff_key($data, array_flip($excluded));

        // Ajouter les timestamps
        if (isset($model->updated_at)) {
            $data['updated_at'] = $model->updated_at->toIso8601String();
        }
        if ($operation === 'create' && isset($model->created_at)) {
            $data['created_at'] = $model->created_at->toIso8601String();
        }

        return $data;
    }

    /**
     * Générer un hash unique
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
