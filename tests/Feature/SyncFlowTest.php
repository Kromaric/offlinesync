<?php

namespace VendorName\OfflineSync\Tests\Feature;

use VendorName\OfflineSync\Tests\TestCase;
use VendorName\OfflineSync\SyncEngine;
use VendorName\OfflineSync\QueueManager;
use VendorName\OfflineSync\ConnectivityService;
use VendorName\OfflineSync\Models\SyncQueueItem;
use VendorName\OfflineSync\Models\SyncLog;
use VendorName\OfflineSync\Events\SyncStarted;
use VendorName\OfflineSync\Events\SyncCompleted;
use VendorName\OfflineSync\Events\ItemSynced;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class SyncFlowTest extends TestCase
{
    protected SyncEngine $syncEngine;
    protected QueueManager $queueManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncEngine = app(SyncEngine::class);
        $this->queueManager = app(QueueManager::class);
    }

    /** @test */
    public function it_completes_full_sync_flow_successfully()
    {
        Event::fake();

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Create pending items
        $item1 = SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '1',
            'operation' => 'create',
            'payload' => ['title' => 'Task 1'],
            'hash' => md5('task1'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $item2 = SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '2',
            'operation' => 'update',
            'payload' => ['title' => 'Task 2'],
            'hash' => md5('task2'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // Mock successful HTTP response
        Http::fake([
            '*/sync/push' => Http::response([
                'success' => true,
                'synced' => 2,
                'failed' => 0,
                'conflicts' => [],
            ]),
        ]);

        // Execute sync
        $result = $this->syncEngine->push();

        // Assertions
        $this->assertEquals(2, $result['synced']);
        $this->assertEquals(0, $result['failed']);

        // Verify events
        Event::assertDispatched(ItemSynced::class, 2);

        // Verify items are marked as synced
        $this->assertEquals('synced', $item1->fresh()->status);
        $this->assertEquals('synced', $item2->fresh()->status);
        $this->assertNotNull($item1->fresh()->synced_at);
        $this->assertNotNull($item2->fresh()->synced_at);
    }

    /** @test */
    public function it_handles_partial_sync_failures()
    {
        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Create pending items
        SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '1',
            'operation' => 'create',
            'payload' => ['title' => 'Task 1'],
            'hash' => md5('task1'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // Mock HTTP response with partial failure
        Http::fake([
            '*/sync/push' => Http::response([
                'success' => false,
                'synced' => 0,
                'failed' => 1,
                'errors' => [
                    [
                        'item' => ['resource' => 'tasks'],
                        'error' => 'Validation failed',
                    ],
                ],
            ]),
        ]);

        $result = $this->syncEngine->push();

        $this->assertEquals(0, $result['synced']);
        $this->assertGreaterThan(0, $result['failed']);
    }

    /** @test */
    public function it_creates_sync_log_for_each_sync_operation()
    {
        config(['offline-sync.logging.enabled' => true]);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Mock HTTP
        Http::fake([
            '*/sync/push' => Http::response([
                'success' => true,
                'synced' => 0,
                'failed' => 0,
            ]),
        ]);

        $initialLogCount = SyncLog::count();

        $this->syncEngine->sync();

        $this->assertEquals($initialLogCount + 1, SyncLog::count());

        $log = SyncLog::latest()->first();
        $this->assertEquals('bidirectional', $log->direction);
        $this->assertNotNull($log->synced_at);
        $this->assertNotNull($log->duration_ms);
    }

    /** @test */
    public function it_retries_failed_items_up_to_max_attempts()
    {
        config(['offline-sync.max_retry_attempts' => 3]);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Create item
        $item = SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '1',
            'operation' => 'create',
            'payload' => ['title' => 'Task 1'],
            'hash' => md5('task1'),
            'status' => 'pending',
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        // Mock HTTP to always fail
        Http::fake([
            '*/sync/push' => Http::response([
                'success' => false,
                'synced' => 0,
                'failed' => 1,
            ], 500),
        ]);

        // Try sync 3 times
        for ($i = 0; $i < 3; $i++) {
            $this->syncEngine->push();
            $item->refresh();
        }

        $this->assertEquals(3, $item->retry_count);
        $this->assertEquals('failed', $item->status);

        // Item should still be in pending query until max retries exceeded
        $pending = $this->queueManager->getPending();
        
        // After 3 retries, should not be in pending anymore
        $this->assertFalse($pending->contains($item));
    }

    /** @test */
    public function it_processes_queue_in_correct_order()
    {
        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Create items with different timestamps
        $item1 = SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '1',
            'operation' => 'create',
            'payload' => ['title' => 'First'],
            'hash' => md5('first'),
            'status' => 'pending',
            'created_at' => now()->subMinutes(3),
        ]);

        $item2 = SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '2',
            'operation' => 'create',
            'payload' => ['title' => 'Second'],
            'hash' => md5('second'),
            'status' => 'pending',
            'created_at' => now()->subMinutes(2),
        ]);

        $item3 = SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '3',
            'operation' => 'create',
            'payload' => ['title' => 'Third'],
            'hash' => md5('third'),
            'status' => 'pending',
            'created_at' => now()->subMinutes(1),
        ]);

        // Track order of items sent
        $sentOrder = [];
        Http::fake([
            '*/sync/push' => function ($request) use (&$sentOrder) {
                $items = $request['items'];
                foreach ($items as $item) {
                    $sentOrder[] = $item['data']['title'];
                }
                return Http::response([
                    'success' => true,
                    'synced' => count($items),
                    'failed' => 0,
                ]);
            },
        ]);

        $this->syncEngine->push();

        // Should be in chronological order (oldest first)
        $this->assertEquals(['First', 'Second', 'Third'], $sentOrder);
    }

    /** @test */
    public function it_handles_empty_queue_gracefully()
    {
        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Ensure queue is empty
        SyncQueueItem::truncate();

        // Should not make any HTTP requests
        Http::fake();

        $result = $this->syncEngine->push();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['failed']);

        Http::assertNothingSent();
    }

    /** @test */
    public function it_supports_resource_filtering()
    {
        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Create items for different resources
        SyncQueueItem::create([
            'resource' => 'tasks',
            'operation' => 'create',
            'payload' => ['title' => 'Task'],
            'hash' => md5('task'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        SyncQueueItem::create([
            'resource' => 'users',
            'operation' => 'create',
            'payload' => ['name' => 'User'],
            'hash' => md5('user'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // Track which resources were sent
        $sentResources = [];
        Http::fake([
            '*/sync/push' => function ($request) use (&$sentResources) {
                foreach ($request['items'] as $item) {
                    $sentResources[] = $item['resource'];
                }
                return Http::response([
                    'success' => true,
                    'synced' => count($request['items']),
                    'failed' => 0,
                ]);
            },
        ]);

        // Sync only tasks
        $this->syncEngine->push(['tasks']);

        $this->assertContains('tasks', $sentResources);
        $this->assertNotContains('users', $sentResources);
    }
}
