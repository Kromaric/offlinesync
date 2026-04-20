<?php

namespace Techparse\OfflineSync\Tests\Unit;

use Techparse\OfflineSync\Tests\TestCase;
use Techparse\OfflineSync\SyncEngine;
use Techparse\OfflineSync\ConflictResolver;
use Techparse\OfflineSync\ConnectivityService;
use Techparse\OfflineSync\QueueManager;
use Techparse\OfflineSync\Models\SyncQueueItem;
use Techparse\OfflineSync\Models\SyncLog;
use Techparse\OfflineSync\Events\SyncStarted;
use Techparse\OfflineSync\Events\SyncCompleted;
use Techparse\OfflineSync\Events\SyncFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class SyncEngineTest extends TestCase
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
    public function it_throws_exception_when_offline_during_push()
    {
        // Mock connectivity service to return offline
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(false);
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No internet connection');

        $this->syncEngine->push();
    }

    /** @test */
    public function it_throws_exception_when_offline_during_pull()
    {
        // Mock connectivity service to return offline
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(false);
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No internet connection');

        $this->syncEngine->pull(['tasks']);
    }

    /** @test */
    public function it_returns_zero_results_when_queue_is_empty()
    {
        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Ensure queue is empty
        SyncQueueItem::truncate();

        $result = $this->syncEngine->push();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['failed']);
    }

    /** @test */
    public function it_dispatches_sync_started_event()
    {
        Event::fake([SyncStarted::class]);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        try {
            $this->syncEngine->sync(['tasks']);
        } catch (\Exception $e) {
            // Ignore errors, we're just testing event dispatch
        }

        Event::assertDispatched(SyncStarted::class, function ($event) {
            return $event->resources === ['tasks'] && $event->direction === 'bidirectional';
        });
    }

    /** @test */
    public function it_dispatches_sync_completed_event_on_success()
    {
        Event::fake([SyncCompleted::class]);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Mock HTTP responses
        Http::fake([
            '*/sync/push' => Http::response([
                'success' => true,
                'synced' => 0,
                'failed' => 0,
                'conflicts' => 0,
            ]),
        ]);

        $this->syncEngine->sync();

        Event::assertDispatched(SyncCompleted::class, function ($event) {
            return $event->synced >= 0 && $event->failed >= 0;
        });
    }

    /** @test */
    public function it_dispatches_sync_failed_event_on_error()
    {
        Event::fake([SyncFailed::class]);

        // Mock connectivity to throw exception
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(false);
        });

        try {
            $this->syncEngine->sync();
        } catch (\Exception $e) {
            // Expected
        }

        Event::assertDispatched(SyncFailed::class);
    }

    /** @test */
    public function it_creates_sync_log_on_successful_sync()
    {
        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Mock HTTP responses
        Http::fake([
            '*/sync/push' => Http::response([
                'success' => true,
                'synced' => 5,
                'failed' => 0,
                'conflicts' => 0,
            ]),
        ]);

        config(['offline-sync.logging.enabled' => true]);

        $this->syncEngine->sync();

        $this->assertDatabaseHas('offline_sync_logs', [
            'direction' => 'bidirectional',
            'success' => true,
        ]);

        $log = SyncLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->success);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
    }

    /** @test */
    public function it_returns_correct_status()
    {
        // Create some pending items
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
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $status = $this->syncEngine->getStatus();

        $this->assertArrayHasKey('pending_count', $status);
        $this->assertArrayHasKey('last_sync', $status);
        $this->assertArrayHasKey('is_syncing', $status);
        $this->assertArrayHasKey('is_online', $status);

        $this->assertEquals(2, $status['pending_count']);
        $this->assertTrue($status['is_online']);
        $this->assertFalse($status['is_syncing']);
    }

    /** @test */
    public function it_enforces_https_when_configured()
    {
        config(['offline-sync.security.require_https' => true]);
        config(['offline-sync.api_url' => 'http://insecure-api.com']);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('HTTPS is required');

        $this->syncEngine->push();
    }

    /** @test */
    public function it_batches_items_according_to_configuration()
    {
        config(['offline-sync.performance.batch_size' => 2]);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Create 5 items (should be sent in 3 batches: 2, 2, 1)
        for ($i = 1; $i <= 5; $i++) {
            SyncQueueItem::create([
                'resource' => 'tasks',
                'resource_id' => (string)$i,
                'operation' => 'create',
                'payload' => ['title' => "Task $i"],
                'hash' => md5("task$i"),
                'status' => 'pending',
                'created_at' => now(),
            ]);
        }

        // Mock HTTP to track number of requests
        $requestCount = 0;
        Http::fake([
            '*/sync/push' => function () use (&$requestCount) {
                $requestCount++;
                return Http::response([
                    'success' => true,
                    'synced' => 2,
                    'failed' => 0,
                ]);
            },
        ]);

        $this->syncEngine->push();

        // Should have made 3 requests (5 items / batch size of 2)
        $this->assertEquals(3, $requestCount);
    }
}
