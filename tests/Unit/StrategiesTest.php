<?php

namespace VendorName\OfflineSync\Tests\Unit;

use VendorName\OfflineSync\Tests\TestCase;
use VendorName\OfflineSync\Strategies\ServerWinsStrategy;
use VendorName\OfflineSync\Strategies\ClientWinsStrategy;
use VendorName\OfflineSync\Strategies\LastWriteWinsStrategy;
use VendorName\OfflineSync\Strategies\MergeStrategy;

class StrategiesTest extends TestCase
{
    /** @test */
    public function server_wins_strategy_always_returns_remote_data()
    {
        $strategy = new ServerWinsStrategy();

        $conflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local Title', 'completed' => false],
            'remote_data' => ['title' => 'Remote Title', 'completed' => true],
            'local_timestamp' => '2025-02-06T10:00:00Z',
            'remote_timestamp' => '2025-02-06T09:00:00Z', // Even if older
        ];

        $result = $strategy->resolve($conflict);

        $this->assertEquals('server_wins', $strategy->name());
        $this->assertEquals('server', $result['winner']);
        $this->assertEquals('overwrite_local', $result['action']);
        $this->assertEquals($conflict['remote_data'], $result['data']);
    }

    /** @test */
    public function client_wins_strategy_always_returns_local_data()
    {
        $strategy = new ClientWinsStrategy();

        $conflict = [
            'resource' => 'tasks',
            'resource_id' => '1',
            'local_data' => ['title' => 'Local Title', 'completed' => false],
            'remote_data' => ['title' => 'Remote Title', 'completed' => true],
            'local_timestamp' => '2025-02-06T09:00:00Z', // Even if older
            'remote_timestamp' => '2025-02-06T10:00:00Z',
        ];

        $result = $strategy->resolve($conflict);

        $this->assertEquals('client_wins', $strategy->name());
        $this->assertEquals('client', $result['winner']);
        $this->assertEquals('force_push', $result['action']);
        $this->assertEquals($conflict['local_data'], $result['data']);
    }

    /** @test */
    public function last_write_wins_strategy_returns_newer_data_based_on_timestamp()
    {
        $strategy = new LastWriteWinsStrategy();

        // Remote is newer
        $conflict1 = [
            'local_data' => ['title' => 'Local'],
            'remote_data' => ['title' => 'Remote'],
            'local_timestamp' => '2025-02-06T10:00:00Z',
            'remote_timestamp' => '2025-02-06T11:00:00Z', // Newer
        ];

        $result1 = $strategy->resolve($conflict1);

        $this->assertEquals('last_write_wins', $strategy->name());
        $this->assertEquals('server', $result1['winner']);
        $this->assertEquals('overwrite_local', $result1['action']);
        $this->assertEquals('remote_newer', $result1['reason']);
        $this->assertEquals($conflict1['remote_data'], $result1['data']);

        // Local is newer
        $conflict2 = [
            'local_data' => ['title' => 'Local'],
            'remote_data' => ['title' => 'Remote'],
            'local_timestamp' => '2025-02-06T11:00:00Z', // Newer
            'remote_timestamp' => '2025-02-06T10:00:00Z',
        ];

        $result2 = $strategy->resolve($conflict2);

        $this->assertEquals('client', $result2['winner']);
        $this->assertEquals('force_push', $result2['action']);
        $this->assertEquals('local_newer', $result2['reason']);
        $this->assertEquals($conflict2['local_data'], $result2['data']);
    }

    /** @test */
    public function last_write_wins_prefers_remote_when_timestamps_are_equal()
    {
        $strategy = new LastWriteWinsStrategy();

        $conflict = [
            'local_data' => ['title' => 'Local'],
            'remote_data' => ['title' => 'Remote'],
            'local_timestamp' => '2025-02-06T10:00:00Z',
            'remote_timestamp' => '2025-02-06T10:00:00Z', // Same timestamp
        ];

        $result = $strategy->resolve($conflict);

        // When equal, remote wins (server is source of truth)
        $this->assertEquals('server', $result['winner']);
        $this->assertEquals($conflict['remote_data'], $result['data']);
    }

    /** @test */
    public function merge_strategy_combines_non_null_values()
    {
        $strategy = new MergeStrategy();

        $conflict = [
            'local_data' => [
                'title' => 'Local Title',
                'description' => null,
                'completed' => true,
                'priority' => 'high',
            ],
            'remote_data' => [
                'title' => 'Remote Title',
                'description' => 'Remote Description',
                'completed' => false,
                'priority' => null,
            ],
        ];

        $result = $strategy->resolve($conflict);

        $this->assertEquals('merge', $strategy->name());
        $this->assertEquals('merged', $result['winner']);
        $this->assertEquals('merge_and_push', $result['action']);

        // Should prefer non-null values from local
        $this->assertEquals('Remote Title', $result['data']['title']); // Remote base
        $this->assertEquals('Remote Description', $result['data']['description']); // Remote non-null
        $this->assertEquals(true, $result['data']['completed']); // Local non-null override
        $this->assertEquals('high', $result['data']['priority']); // Local non-null override

        // Should list conflicting keys
        $this->assertArrayHasKey('conflicts_merged', $result);
        $this->assertContains('title', $result['conflicts_merged']);
        $this->assertContains('completed', $result['conflicts_merged']);
        $this->assertContains('priority', $result['conflicts_merged']);
    }

    /** @test */
    public function merge_strategy_handles_empty_local_data()
    {
        $strategy = new MergeStrategy();

        $conflict = [
            'local_data' => [],
            'remote_data' => ['title' => 'Remote', 'completed' => true],
        ];

        $result = $strategy->resolve($conflict);

        $this->assertEquals($conflict['remote_data'], $result['data']);
    }

    /** @test */
    public function merge_strategy_handles_empty_remote_data()
    {
        $strategy = new MergeStrategy();

        $conflict = [
            'local_data' => ['title' => 'Local', 'completed' => false],
            'remote_data' => [],
        ];

        $result = $strategy->resolve($conflict);

        // Merged should be local data since remote is empty
        $this->assertEquals('Local', $result['data']['title']);
        $this->assertEquals(false, $result['data']['completed']);
    }

    /** @test */
    public function all_strategies_implement_required_interface()
    {
        $strategies = [
            new ServerWinsStrategy(),
            new ClientWinsStrategy(),
            new LastWriteWinsStrategy(),
            new MergeStrategy(),
        ];

        foreach ($strategies as $strategy) {
            $this->assertIsString($strategy->name());
            $this->assertNotEmpty($strategy->name());
            
            // Test that resolve returns required structure
            $result = $strategy->resolve([
                'local_data' => ['test' => 'local'],
                'remote_data' => ['test' => 'remote'],
                'local_timestamp' => '2025-02-06T10:00:00Z',
                'remote_timestamp' => '2025-02-06T10:00:00Z',
            ]);

            $this->assertArrayHasKey('data', $result);
            $this->assertArrayHasKey('winner', $result);
            $this->assertArrayHasKey('action', $result);
        }
    }
}
