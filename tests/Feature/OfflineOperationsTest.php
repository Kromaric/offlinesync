<?php

namespace VendorName\OfflineSync\Tests\Feature;

use VendorName\OfflineSync\Tests\TestCase;
use VendorName\OfflineSync\QueueManager;
use VendorName\OfflineSync\Models\SyncQueueItem;
use VendorName\OfflineSync\Events\ItemQueued;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

class OfflineOperationsTest extends TestCase
{
    protected QueueManager $queueManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueManager = app(QueueManager::class);
    }

    /** @test */
    public function it_queues_create_operation()
    {
        Event::fake([ItemQueued::class]);

        $model = $this->createMockModel([
            'id' => 1,
            'title' => 'New Task',
            'completed' => false,
        ]);

        $item = $this->queueManager->queue($model, 'create');

        $this->assertInstanceOf(SyncQueueItem::class, $item);
        $this->assertEquals('tasks', $item->resource);
        $this->assertNull($item->resource_id); // Create has no ID yet
        $this->assertEquals('create', $item->operation);
        $this->assertEquals('pending', $item->status);
        $this->assertIsArray($item->payload);
        $this->assertArrayHasKey('title', $item->payload);

        Event::assertDispatched(ItemQueued::class);
    }

    /** @test */
    public function it_queues_update_operation()
    {
        $model = $this->createMockModel([
            'id' => 1,
            'title' => 'Updated Task',
        ], ['title' => 'Updated Task']); // dirty = title changed

        $item = $this->queueManager->queue($model, 'update');

        $this->assertEquals('tasks', $item->resource);
        $this->assertEquals('1', $item->resource_id);
        $this->assertEquals('update', $item->operation);
        $this->assertArrayHasKey('title', $item->payload);
        $this->assertEquals('Updated Task', $item->payload['title']);
    }

    /** @test */
    public function it_queues_delete_operation()
    {
        $model = $this->createMockModel([
            'id' => 1,
            'title' => 'Task to Delete',
        ]);

        $item = $this->queueManager->queue($model, 'delete');

        $this->assertEquals('delete', $item->operation);
        $this->assertArrayHasKey('id', $item->payload);
        $this->assertArrayHasKey('deleted_at', $item->payload);
    }

    /** @test */
    public function it_prevents_duplicate_queue_entries()
    {
        $model = $this->createMockModel([
            'id' => 1,
            'title' => 'Task',
        ]);

        // Queue same operation twice
        $item1 = $this->queueManager->queue($model, 'create');
        $item2 = $this->queueManager->queue($model, 'create');

        // Should be the same item (updateOrCreate)
        $this->assertEquals($item1->id, $item2->id);
        $this->assertEquals(1, SyncQueueItem::count());
    }

    /** @test */
    public function it_includes_timestamps_in_payload()
    {
        $model = $this->createMockModel([
            'id' => 1,
            'title' => 'Task',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $item = $this->queueManager->queue($model, 'create');

        $this->assertArrayHasKey('created_at', $item->payload);
        $this->assertArrayHasKey('updated_at', $item->payload);
        $this->assertStringContainsString('T', $item->payload['created_at']); // ISO8601 format
    }

    /** @test */
    public function it_excludes_configured_fields_from_payload()
    {
        $model = $this->createMockModel([
            'id' => 1,
            'title' => 'Task',
            'internal_notes' => 'Secret',
            'password' => 'secret123',
        ]);

        // Mock getSyncExcluded
        $model->shouldReceive('getSyncExcluded')->andReturn(['internal_notes', 'password']);

        $item = $this->queueManager->queue($model, 'create');

        $this->assertArrayHasKey('title', $item->payload);
        $this->assertArrayNotHasKey('internal_notes', $item->payload);
        $this->assertArrayNotHasKey('password', $item->payload);
    }

    /** @test */
    public function it_gets_only_pending_items()
    {
        // Create items with different statuses
        SyncQueueItem::create([
            'resource' => 'tasks',
            'operation' => 'create',
            'payload' => ['title' => 'Pending'],
            'hash' => md5('pending'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        SyncQueueItem::create([
            'resource' => 'tasks',
            'operation' => 'create',
            'payload' => ['title' => 'Synced'],
            'hash' => md5('synced'),
            'status' => 'synced',
            'synced_at' => now(),
            'created_at' => now(),
        ]);

        SyncQueueItem::create([
            'resource' => 'tasks',
            'operation' => 'create',
            'payload' => ['title' => 'Failed but retryable'],
            'hash' => md5('failed'),
            'status' => 'failed',
            'retry_count' => 1,
            'created_at' => now(),
        ]);

        $pending = $this->queueManager->getPending();

        // Should get pending + failed (but retryable)
        $this->assertEquals(2, $pending->count());
        $this->assertFalse($pending->contains('status', 'synced'));
    }

    /** @test */
    public function it_filters_pending_items_by_resource()
    {
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

        $tasksPending = $this->queueManager->getPending('tasks');
        $usersPending = $this->queueManager->getPending('users');

        $this->assertEquals(1, $tasksPending->count());
        $this->assertEquals('tasks', $tasksPending->first()->resource);

        $this->assertEquals(1, $usersPending->count());
        $this->assertEquals('users', $usersPending->first()->resource);
    }

    /** @test */
    public function it_purges_old_synced_items()
    {
        // Create old synced item
        SyncQueueItem::create([
            'resource' => 'tasks',
            'operation' => 'create',
            'payload' => ['title' => 'Old'],
            'hash' => md5('old'),
            'status' => 'synced',
            'created_at' => now()->subDays(10),
            'synced_at' => now()->subDays(10),
        ]);

        // Create recent synced item
        SyncQueueItem::create([
            'resource' => 'tasks',
            'operation' => 'create',
            'payload' => ['title' => 'Recent'],
            'hash' => md5('recent'),
            'status' => 'synced',
            'created_at' => now()->subDays(3),
            'synced_at' => now()->subDays(3),
        ]);

        // Create pending item
        SyncQueueItem::create([
            'resource' => 'tasks',
            'operation' => 'create',
            'payload' => ['title' => 'Pending'],
            'hash' => md5('pending'),
            'status' => 'pending',
            'created_at' => now()->subDays(10),
        ]);

        $purged = $this->queueManager->purgeOldItems(7);

        // Should purge only old synced item
        $this->assertEquals(1, $purged);
        $this->assertEquals(2, SyncQueueItem::count());
        $this->assertDatabaseHas('offline_sync_queue', [
            'hash' => md5('recent'),
        ]);
        $this->assertDatabaseHas('offline_sync_queue', [
            'hash' => md5('pending'),
        ]);
        $this->assertDatabaseMissing('offline_sync_queue', [
            'hash' => md5('old'),
        ]);
    }

    /** @test */
    public function it_generates_unique_hash_for_each_operation()
    {
        $model = $this->createMockModel(['id' => 1, 'title' => 'Task']);

        $item1 = $this->queueManager->queue($model, 'create');
        
        // Change data
        $model = $this->createMockModel(['id' => 1, 'title' => 'Task Updated']);
        $item2 = $this->queueManager->queue($model, 'update');

        $this->assertNotEquals($item1->hash, $item2->hash);
    }

    /**
     * Create a mock model for testing
     */
    protected function createMockModel(array $attributes, array $dirty = []): Model
    {
        $model = \Mockery::mock(Model::class);
        
        $model->shouldReceive('getSyncResourceName')->andReturn('tasks');
        $model->shouldReceive('getKey')->andReturn($attributes['id'] ?? 1);
        $model->shouldReceive('getAttributes')->andReturn($attributes);
        $model->shouldReceive('getDirty')->andReturn($dirty ?: $attributes);
        $model->shouldReceive('getSyncExcluded')->andReturn([]);
        
        // Mock timestamps
        if (isset($attributes['created_at'])) {
            $model->created_at = $attributes['created_at'];
        }
        if (isset($attributes['updated_at'])) {
            $model->updated_at = $attributes['updated_at'];
        }

        return $model;
    }
}
