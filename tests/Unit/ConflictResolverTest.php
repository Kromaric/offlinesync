<?php

namespace VendorName\OfflineSync\Tests\Unit;

use VendorName\OfflineSync\Tests\TestCase;
use VendorName\OfflineSync\ConflictResolver;
use VendorName\OfflineSync\Strategies\ServerWinsStrategy;
use VendorName\OfflineSync\Strategies\ClientWinsStrategy;
use VendorName\OfflineSync\Strategies\LastWriteWinsStrategy;
use VendorName\OfflineSync\Strategies\MergeStrategy;
use VendorName\OfflineSync\Events\ConflictDetected;
use Illuminate\Support\Facades\Event;

class ConflictResolverTest extends TestCase
{
    protected ConflictResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ConflictResolver::class);
    }

    /** @test */
    public function it_uses_default_strategy_when_no_specific_strategy_configured()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'server_wins']);
        config(['offline-sync.conflict_resolution.per_resource' => []]);

        $conflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local Title'],
            'remote_data' => ['title' => 'Remote Title'],
            'local_timestamp' => '2025-02-06T10:00:00Z',
            'remote_timestamp' => '2025-02-06T11:00:00Z',
        ];

        $result = $this->resolver->resolve($conflict);

        $this->assertEquals('server', $result['winner']);
        $this->assertEquals($conflict['remote_data'], $result['data']);
    }

    /** @test */
    public function it_uses_per_resource_strategy_when_configured()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'server_wins']);
        config(['offline-sync.conflict_resolution.per_resource' => [
            'tasks' => 'client_wins',
        ]]);

        $conflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local Title'],
            'remote_data' => ['title' => 'Remote Title'],
        ];

        $result = $this->resolver->resolve($conflict);

        $this->assertEquals('client', $result['winner']);
        $this->assertEquals($conflict['local_data'], $result['data']);
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
            return $event->conflict === $conflict;
        });
    }

    /** @test */
    public function it_falls_back_to_server_wins_for_unknown_strategy()
    {
        config(['offline-sync.conflict_resolution.default_strategy' => 'unknown_strategy']);

        $conflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local'],
            'remote_data' => ['title' => 'Remote'],
        ];

        $result = $this->resolver->resolve($conflict);

        // Should fall back to server_wins
        $this->assertEquals('server', $result['winner']);
        $this->assertEquals($conflict['remote_data'], $result['data']);
    }

    /** @test */
    public function it_resolves_conflicts_for_multiple_resources()
    {
        config(['offline-sync.conflict_resolution.per_resource' => [
            'tasks' => 'client_wins',
            'users' => 'server_wins',
        ]]);

        // Task conflict - should use client_wins
        $taskConflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local Task'],
            'remote_data' => ['title' => 'Remote Task'],
        ];

        $taskResult = $this->resolver->resolve($taskConflict);
        $this->assertEquals('client', $taskResult['winner']);

        // User conflict - should use server_wins
        $userConflict = [
            'resource' => 'users',
            'resource_id' => '1',
            'local_data' => ['name' => 'Local User'],
            'remote_data' => ['name' => 'Remote User'],
        ];

        $userResult = $this->resolver->resolve($userConflict);
        $this->assertEquals('server', $userResult['winner']);
    }
}
