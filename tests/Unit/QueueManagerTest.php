<?php

namespace Techparse\OfflineSync\Tests\Unit;

use Techparse\OfflineSync\Tests\TestCase;
use Techparse\OfflineSync\QueueManager;
use Techparse\OfflineSync\Models\SyncQueueItem;

class QueueManagerTest extends TestCase
{
    protected QueueManager $queueManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueManager = app(QueueManager::class);
    }

    /** @test */
    public function it_gets_pending_items()
    {
        // Créer des items de test
        SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '1',
            'operation' => 'create',
            'payload' => ['title' => 'Test'],
            'hash' => md5('test1'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '2',
            'operation' => 'update',
            'payload' => ['title' => 'Test 2'],
            'hash' => md5('test2'),
            'status' => 'synced',
            'created_at' => now(),
            'synced_at' => now(),
        ]);
        
        $pending = $this->queueManager->getPending();
        
        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    /** @test */
    public function it_purges_old_synced_items()
    {
        SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '1',
            'operation' => 'create',
            'payload' => ['title' => 'Old'],
            'hash' => md5('old'),
            'status' => 'synced',
            'created_at' => now()->subDays(10),
            'synced_at' => now()->subDays(10),
        ]);
        
        $count = $this->queueManager->purgeOldItems(7);
        
        $this->assertEquals(1, $count);
        $this->assertEquals(0, SyncQueueItem::count());
    }
}
