<?php

namespace Techparse\OfflineSync\Commands;

use Illuminate\Console\Command;
use Techparse\OfflineSync\SyncEngine;

class SyncPull extends Command
{
    protected $signature = 'sync:pull {resources* : Resources to sync}';
    protected $description = 'Pull remote changes from the server';

    public function handle(SyncEngine $syncEngine): int
    {
        $resources = $this->argument('resources');
        
        if (empty($resources)) {
            $this->error('Please specify at least one resource');
            return 1;
        }
        
        $this->info('Starting pull sync...');
        
        try {
            $result = $syncEngine->pull($resources);
            
            $this->info("Pulled {$result['synced']} items");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}
