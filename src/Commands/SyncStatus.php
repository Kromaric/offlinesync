<?php

namespace Techparse\OfflineSync\Commands;

use Illuminate\Console\Command;
use Techparse\OfflineSync\QueueManager;

class SyncStatus extends Command
{
    protected $signature = 'sync:status';
    protected $description = 'Show sync queue status';

    public function handle(QueueManager $queueManager): int
    {
        $pending = $queueManager->getPending();
        
        if ($pending->isEmpty()) {
            $this->info('No pending items in sync queue');
            return 0;
        }
        
        $this->table(
            ['ID', 'Resource', 'Operation', 'Status', 'Created', 'Retries'],
            $pending->map(fn($item) => [
                $item->id,
                $item->resource,
                $item->operation,
                $item->status,
                $item->created_at->diffForHumans(),
                $item->retry_count,
            ])
        );
        
        $this->info("Total pending: {$pending->count()}");
        
        return 0;
    }
}
