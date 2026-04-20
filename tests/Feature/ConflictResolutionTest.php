<?php

namespace Techparse\OfflineSync\Tests\Feature;

use Techparse\OfflineSync\Tests\TestCase;
use Techparse\OfflineSync\SyncEngine;
use Techparse\OfflineSync\ConnectivityService;
use Techparse\OfflineSync\ConflictResolver;
use Techparse\OfflineSync\Models\SyncQueueItem;
use Techparse\OfflineSync\Events\ConflictDetected;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class ConflictResolutionTest extends TestCase
{
    protected SyncEngine $syncEngine;
    protected ConflictResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncEngine = app(SyncEngine::class);
        $this->resolver = app(ConflictResolver::class);
    }

    /** @test */
    public function it_resolves_conflict_with_server_wins_strategy()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'server_wins']);

        $conflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local Title', 'completed' => false],
            'remote_data' => ['title' => 'Remote Title', 'completed' => true],
            'local_timestamp' => '2025-02-06T10:00:00Z',
            'remote_timestamp' => '2025-02-06T09:00:00Z', // Remote is older
        ];

        $result = $this->resolver->resolve($conflict);

        // Server wins regardless of timestamp
        $this->assertEquals('server', $result['winner']);
        $this->assertEquals($conflict['remote_data'], $result['data']);
        $this->assertEquals('overwrite_local', $result['action']);
    }

    /** @test */
    public function it_resolves_conflict_with_client_wins_strategy()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'client_wins']);

        $conflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local Title', 'completed' => false],
            'remote_data' => ['title' => 'Remote Title', 'completed' => true],
            'local_timestamp' => '2025-02-06T09:00:00Z', // Local is older
            'remote_timestamp' => '2025-02-06T10:00:00Z',
        ];

        $result = $this->resolver->resolve($conflict);

        // Client wins regardless of timestamp
        $this->assertEquals('client', $result['winner']);
        $this->assertEquals($conflict['local_data'], $result['data']);
        $this->assertEquals('force_push', $result['action']);
    }

    /** @test */
    public function it_resolves_conflict_with_last_write_wins_strategy()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'last_write_wins']);

        // Test 1: Remote is newer
        $conflict1 = [
            'resource' => 'tasks',
            'local_data' => ['title' => 'Local'],
            'remote_data' => ['title' => 'Remote'],
            'local_timestamp' => '2025-02-06T10:00:00Z',
            'remote_timestamp' => '2025-02-06T11:00:00Z', // Newer
        ];

        $result1 = $this->resolver->resolve($conflict1);
        $this->assertEquals('server', $result1['winner']);
        $this->assertEquals('remote_newer', $result1['reason']);

        // Test 2: Local is newer
        $conflict2 = [
            'resource' => 'tasks',
            'local_data' => ['title' => 'Local'],
            'remote_data' => ['title' => 'Remote'],
            'local_timestamp' => '2025-02-06T11:00:00Z', // Newer
            'remote_timestamp' => '2025-02-06T10:00:00Z',
        ];

        $result2 = $this->resolver->resolve($conflict2);
        $this->assertEquals('client', $result2['winner']);
        $this->assertEquals('local_newer', $result2['reason']);
    }

    /** @test */
    public function it_resolves_conflict_with_merge_strategy()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'merge']);

        $conflict = [
            'resource' => 'tasks',
            'local_data' => [
                'title' => 'Local Title',
                'description' => 'Local description',
                'completed' => true,
                'priority' => null,
            ],
            'remote_data' => [
                'title' => 'Remote Title',
                'description' => null,
                'completed' => false,
                'priority' => 'high',
            ],
        ];

        $result = $this->resolver->resolve($conflict);

        $this->assertEquals('merged', $result['winner']);
        $this->assertEquals('merge_and_push', $result['action']);

        // Check merged data (non-null local values should override)
        $merged = $result['data'];
        $this->assertEquals('Local description', $merged['description']); // Local non-null wins
        $this->assertEquals(true, $merged['completed']); // Local non-null wins
        $this->assertEquals('high', $merged['priority']); // Remote non-null wins
    }

    /** @test */
    public function it_uses_per_resource_conflict_strategy()
    {
        config([
            'offline-sync.conflict_resolution.default_strategy' => 'server_wins',
            'offline-sync.conflict_resolution.per_resource' => [
                'tasks' => 'client_wins',
                'users' => 'last_write_wins',
            ],
        ]);

        // Tasks should use client_wins
        $taskConflict = [
            'resource' => 'tasks',
            'local_data' => ['title' => 'Local Task'],
            'remote_data' => ['title' => 'Remote Task'],
        ];
        
        $taskResult = $this->resolver->resolve($taskConflict);
        $this->assertEquals('client', $taskResult['winner']);

        // Users should use last_write_wins
        $userConflict = [
            'resource' => 'users',
            'local_data' => ['name' => 'Local User'],
            'remote_data' => ['name' => 'Remote User'],
            'local_timestamp' => '2025-02-06T11:00:00Z',
            'remote_timestamp' => '2025-02-06T10:00:00Z',
        ];
        
        $userResult = $this->resolver->resolve($userConflict);
        $this->assertEquals('client', $userResult['winner']);
        $this->assertEquals('local_newer', $userResult['reason']);

        // Projects should use default (server_wins)
        $projectConflict = [
            'resource' => 'projects',
            'local_data' => ['name' => 'Local Project'],
            'remote_data' => ['name' => 'Remote Project'],
        ];
        
        $projectResult = $this->resolver->resolve($projectConflict);
        $this->assertEquals('server', $projectResult['winner']);
    }

    /** @test */
    public function it_dispatches_conflict_detected_event()
    {
        Event::fake([ConflictDetected::class]);

        $conflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local'],
            'remote_data' => ['title' => 'Remote'],
        ];

        $this->resolver->resolve($conflict);

        Event::assertDispatched(ConflictDetected::class, function ($event) use ($conflict) {
            return $event->conflict['resource'] === $conflict['resource'];
        });
    }

    /** @test */
    public function it_handles_conflicts_during_push_sync()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'server_wins']);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Create pending item
        SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '1',
            'operation' => 'update',
            'payload' => ['title' => 'Local Title'],
            'hash' => md5('task1'),
            'status' => 'pending',
            'created_at' => now()->subMinutes(5),
        ]);

        // Mock HTTP response with conflict
        Http::fake([
            '*/sync/push' => Http::response([
                'success' => false,
                'synced' => 0,
                'failed' => 0,
                'conflicts' => [
                    [
                        'resource' => 'tasks',
                        'resource_id' => '1',
                        'local_data' => ['title' => 'Local Title'],
                        'remote_data' => ['title' => 'Remote Title'],
                        'local_timestamp' => now()->subMinutes(5)->toIso8601String(),
                        'remote_timestamp' => now()->toIso8601String(),
                    ],
                ],
            ]),
        ]);

        $result = $this->syncEngine->push();

        $this->assertArrayHasKey('conflicts', $result);
        $this->assertCount(1, $result['conflicts']);
    }

    /** @test */
    public function it_handles_multiple_conflicts_in_single_sync()
    {
        Event::fake([ConflictDetected::class]);

        config(['offline-sync.conflict_resolution.default_strategy' => 'last_write_wins']);

        // Mock connectivity
        $this->mock(ConnectivityService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(true);
        });

        // Create multiple pending items
        SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '1',
            'operation' => 'update',
            'payload' => ['title' => 'Task 1'],
            'hash' => md5('task1'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        SyncQueueItem::create([
            'resource' => 'tasks',
            'resource_id' => '2',
            'operation' => 'update',
            'payload' => ['title' => 'Task 2'],
            'hash' => md5('task2'),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // Mock HTTP response with multiple conflicts
        Http::fake([
            '*/sync/push' => Http::response([
                'success' => false,
                'synced' => 0,
                'failed' => 0,
                'conflicts' => [
                    [
                        'resource' => 'tasks',
                        'resource_id' => '1',
                        'local_data' => ['title' => 'Task 1'],
                        'remote_data' => ['title' => 'Task 1 Remote'],
                        'local_timestamp' => now()->toIso8601String(),
                        'remote_timestamp' => now()->toIso8601String(),
                    ],
                    [
                        'resource' => 'tasks',
                        'resource_id' => '2',
                        'local_data' => ['title' => 'Task 2'],
                        'remote_data' => ['title' => 'Task 2 Remote'],
                        'local_timestamp' => now()->toIso8601String(),
                        'remote_timestamp' => now()->toIso8601String(),
                    ],
                ],
            ]),
        ]);

        $result = $this->syncEngine->push();

        $this->assertCount(2, $result['conflicts']);
    }

    /** @test */
    public function merge_strategy_preserves_all_data_when_no_conflicts()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'merge']);

        $conflict = [
            'resource' => 'tasks',
            'local_data' => ['title' => 'Title', 'completed' => true],
            'remote_data' => ['title' => 'Title', 'completed' => true],
        ];

        $result = $this->resolver->resolve($conflict);

        $this->assertEquals('merged', $result['winner']);
        $this->assertEquals('Title', $result['data']['title']);
        $this->assertEquals(true, $result['data']['completed']);
    }
}
