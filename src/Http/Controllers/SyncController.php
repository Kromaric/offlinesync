<?php

namespace Techparse\OfflineSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Techparse\OfflineSync\ConflictResolver;

class SyncController
{
    protected ConflictResolver $conflictResolver;

    public function __construct(ConflictResolver $conflictResolver)
    {
        $this->conflictResolver = $conflictResolver;
    }

    /**
     * Push : recevoir les changements du client
     */
    public function push(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.resource' => 'required|string',
            'items.*.resource_id' => 'nullable|string',
            'items.*.operation' => 'required|in:create,update,delete',
            'items.*.data' => 'required|array',
            'items.*.timestamp' => 'required|date',
        ]);

        $synced = [];
        $failed = [];
        $conflicts = [];

        DB::beginTransaction();
        
        try {
            foreach ($validated['items'] as $item) {
                try {
                    $result = $this->applyChange($item);
                    
                    if (isset($result['conflict'])) {
                        $conflicts[] = $result['conflict'];
                    } else {
                        $synced[] = $item;
                    }
                } catch (\Exception $e) {
                    $failed[] = [
                        'item' => $item,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => empty($failed),
                'synced' => count($synced),
                'failed' => count($failed),
                'conflicts' => $conflicts,
                'errors' => $failed,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pull : envoyer les changements au client
     */
    public function pull(Request $request, string $resource)
    {
        $validated = $request->validate([
            'since' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $since = $validated['since'] ?? now()->subDays(30);
        $limit = $validated['limit'] ?? 100;

        // Déterminer la classe du modèle
        $modelClass = $this->getModelClass($resource);
        
        if (!$modelClass) {
            return response()->json([
                'error' => 'Resource not found',
            ], 404);
        }

        $items = $modelClass::where('updated_at', '>', $since)
            ->limit($limit)
            ->get()
            ->map(fn($model) => [
                'id' => $model->getKey(),
                'operation' => 'update',
                'data' => $model->toArray(),
                'timestamp' => $model->updated_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => $items,
            'count' => $items->count(),
            'since' => $since,
        ]);
    }

    /**
     * Status : info sur l'état du serveur
     */
    public function status(Request $request)
    {
        return response()->json([
            'server_time' => now()->toIso8601String(),
            'available_resources' => $this->getAvailableResources(),
        ]);
    }

    /**
     * Ping : vérifier la connexion
     */
    public function ping()
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Appliquer un changement individuel
     */
    protected function applyChange(array $item): array
    {
        $modelClass = $this->getModelClass($item['resource']);
        
        if (!$modelClass) {
            throw new \Exception("Resource {$item['resource']} not found");
        }

        $clientTimestamp = \Carbon\Carbon::parse($item['timestamp']);

        // Vérifier conflit pour update/delete
        if (in_array($item['operation'], ['update', 'delete']) && $item['resource_id']) {
            $existing = $modelClass::find($item['resource_id']);
            
            if ($existing && $existing->updated_at > $clientTimestamp) {
                return [
                    'conflict' => [
                        'resource' => $item['resource'],
                        'resource_id' => $item['resource_id'],
                        'local_data' => $item['data'],
                        'remote_data' => $existing->toArray(),
                        'local_timestamp' => $item['timestamp'],
                        'remote_timestamp' => $existing->updated_at->toIso8601String(),
                    ]
                ];
            }
        }

        // Appliquer l'opération
        match($item['operation']) {
            'create' => $modelClass::create($item['data']),
            'update' => $modelClass::updateOrCreate(
                ['id' => $item['resource_id']],
                $item['data']
            ),
            'delete' => $modelClass::destroy($item['resource_id']),
        };

        return ['success' => true];
    }

    /**
     * Obtenir la classe du modèle depuis le nom de ressource
     */
    protected function getModelClass(string $resource): ?string
    {
        $mapping = config('offline-sync.resource_mapping', []);
        return $mapping[$resource] ?? null;
    }

    /**
     * Liste des ressources disponibles
     */
    protected function getAvailableResources(): array
    {
        return array_keys(config('offline-sync.resource_mapping', []));
    }
}
