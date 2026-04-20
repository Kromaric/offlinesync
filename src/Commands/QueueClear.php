<?php

namespace Techparse\OfflineSync\Commands;

use Illuminate\Console\Command;
use Techparse\OfflineSync\Models\SyncQueueItem;

class QueueClear extends Command
{
    protected $signature = 'sync:clear {--failed : Clear only failed items}';
    protected $description = 'Clear sync queue';

    public function handle(): int
    {
        if (!$this->confirm('Are you sure you want to clear the queue?')) {
            return 0;
        }
        
        if ($this->option('failed')) {
            $count = SyncQueueItem::where('status', 'failed')->delete();
            $this->info("Cleared {$count} failed items");
        } else {
            $count = SyncQueueItem::count();
            SyncQueueItem::truncate();
            $this->info("Cleared entire queue ({$count} items)");
        }
        
        return 0;
    }
}
