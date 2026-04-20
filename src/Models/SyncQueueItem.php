<?php

namespace Techparse\OfflineSync\Models;

use Illuminate\Database\Eloquent\Model;

class SyncQueueItem extends Model
{
    public $timestamps = false;
    
    protected $table = 'offline_sync_queue';
    
    protected $fillable = [
        'resource',
        'resource_id',
        'operation',
        'payload',
        'hash',
        'status',
        'retry_count',
        'created_at',
        'synced_at',
        'error_message',
        'error_details',
    ];
    
    protected $casts = [
        'payload' => 'array',
        'error_details' => 'array',
        'created_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->orWhere(function($q) {
                         $q->where('status', 'failed')
                           ->where('retry_count', '<', config('offline-sync.max_retry_attempts', 3));
                     });
    }
    
    public function scopeForResource($query, string $resource)
    {
        return $query->where('resource', $resource);
    }
    
    // Helpers
    public function canRetry(): bool
    {
        return $this->retry_count < config('offline-sync.max_retry_attempts', 3);
    }
    
    public function markAsSynced(): void
    {
        $this->update([
            'status' => 'synced',
            'synced_at' => now(),
            'error_message' => null,
            'error_details' => null,
        ]);
    }
    
    public function markAsFailed(string $message, array $details = []): void
    {
        $this->increment('retry_count');
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
            'error_details' => $details,
        ]);
    }
}
