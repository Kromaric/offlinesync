<?php

namespace Techparse\OfflineSync\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    public $timestamps = false;
    
    protected $table = 'offline_sync_logs';
    
    protected $fillable = [
        'synced_at',
        'direction',
        'items_count',
        'synced_count',
        'failed_count',
        'conflicts_count',
        'duration_ms',
        'success',
        'details',
    ];
    
    protected $casts = [
        'synced_at' => 'datetime',
        'success' => 'boolean',
        'details' => 'array',
    ];
    
    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }
    
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('synced_at', '>=', now()->subDays($days));
    }
    
    // Stats helpers
    public static function getStats(int $days = 7): array
    {
        $logs = static::recent($days)->get();
        
        return [
            'total_syncs' => $logs->count(),
            'successful_syncs' => $logs->where('success', true)->count(),
            'failed_syncs' => $logs->where('success', false)->count(),
            'total_items' => $logs->sum('items_count'),
            'total_conflicts' => $logs->sum('conflicts_count'),
            'avg_duration_ms' => $logs->avg('duration_ms'),
        ];
    }
}
