<?php

namespace Techparse\OfflineSync\Commands;

use Illuminate\Console\Command;
use Techparse\OfflineSync\SyncEngine;

class SyncPush extends Command
{
    protected $signature = 'sync:push {resources?* : Resources to sync}';
    protected $description = 'Push local changes to the server';

    public function handle(SyncEngine $syncEngine): int
    {
        $resources = $this->argument('resources');
        
        $this->info('Starting push sync...');
        
        try {
            $result = $syncEngine->push($resources ?: null);
            
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Synced', $result['synced']],
                    ['Failed', $result['failed']],
                    ['Conflicts', $result['conflicts'] ?? 0],
                ]
            );
            
            return $result['failed'] > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}
