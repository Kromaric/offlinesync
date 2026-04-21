# Tests Documentation

Complete guide to tests for the NativePHP Offline Sync plugin.

## 📋 Table of contents

1. [Test structure](#test-structure)
2. [Running tests](#running-tests)
3. [Unit tests](#unit-tests)
4. [Integration tests](#integration-tests)
5. [Coverage](#coverage)
6. [Writing new tests](#writing-new-tests)

---

## 🗂️ Test structure

```
tests/
├── TestCase.php              # Base class for all tests
├── Unit/                     # Unit tests (isolated components)
│   ├── QueueManagerTest.php
│   ├── ConflictResolverTest.php
│   ├── StrategiesTest.php
│   └── SyncEngineTest.php
└── Feature/                  # Integration tests (full flows)
    ├── SyncFlowTest.php
    ├── OfflineOperationsTest.php
    └── ConflictResolutionTest.php
```

**Total: 8 test files**

---

## 🚀 Running tests

### All tests

```bash
composer test
# or
vendor/bin/phpunit
```

### Unit tests only

```bash
composer test-unit
# or
vendor/bin/phpunit --testsuite Unit
```

### Integration tests only

```bash
composer test-feature
# or
vendor/bin/phpunit --testsuite Feature
```

### Specific test

```bash
composer test-filter -- TestName
# or
vendor/bin/phpunit --filter test_method_name
```

### With HTML coverage

```bash
composer test-coverage
```

Results will be in `coverage/html/index.html`

---

## 🧪 Unit Tests

### QueueManagerTest (2 tests)

Tests queue management:

- ✅ `it_gets_pending_items` — retrieve pending items
- ✅ `it_purges_old_synced_items` — purge old items

**What is tested:**
- Filtering by status (pending vs synced)
- Date-based purge
- Item counting

### ConflictResolverTest (5 tests)

Tests conflict resolution:

- ✅ `it_uses_default_strategy_when_no_specific_strategy_configured`
- ✅ `it_uses_per_resource_strategy_when_configured`
- ✅ `it_dispatches_conflict_detected_event`
- ✅ `it_falls_back_to_server_wins_for_unknown_strategy`
- ✅ `it_resolves_conflicts_for_multiple_resources`

**What is tested:**
- Default strategy
- Per-resource strategies
- Fallback on unknown strategy
- Laravel events

### StrategiesTest (10 tests)

Tests the 4 resolution strategies:

**ServerWins:**
- ✅ `server_wins_strategy_always_returns_remote_data`

**ClientWins:**
- ✅ `client_wins_strategy_always_returns_local_data`

**LastWriteWins:**
- ✅ `last_write_wins_strategy_returns_newer_data_based_on_timestamp`
- ✅ `last_write_wins_prefers_remote_when_timestamps_are_equal`

**Merge:**
- ✅ `merge_strategy_combines_non_null_values`
- ✅ `merge_strategy_handles_empty_local_data`
- ✅ `merge_strategy_handles_empty_remote_data`
- ✅ `merge_strategy_preserves_all_data_when_no_conflicts`

**Interface:**
- ✅ `all_strategies_implement_required_interface`

### SyncEngineTest (10 tests)

Tests the sync engine:

- ✅ `it_throws_exception_when_offline_during_push`
- ✅ `it_throws_exception_when_offline_during_pull`
- ✅ `it_returns_zero_results_when_queue_is_empty`
- ✅ `it_dispatches_sync_started_event`
- ✅ `it_dispatches_sync_completed_event_on_success`
- ✅ `it_dispatches_sync_failed_event_on_error`
- ✅ `it_creates_sync_log_on_successful_sync`
- ✅ `it_returns_correct_status`
- ✅ `it_enforces_https_when_configured`
- ✅ `it_batches_items_according_to_configuration`

**What is tested:**
- Connectivity handling
- Events (started, completed, failed)
- Sync logs
- HTTPS security
- Item batching

---

## 🔄 Integration Tests

### SyncFlowTest (9 tests)

Tests complete sync flows:

- ✅ `it_completes_full_sync_flow_successfully`
- ✅ `it_handles_partial_sync_failures`
- ✅ `it_creates_sync_log_for_each_sync_operation`
- ✅ `it_retries_failed_items_up_to_max_attempts`
- ✅ `it_processes_queue_in_correct_order`
- ✅ `it_handles_empty_queue_gracefully`
- ✅ `it_supports_resource_filtering`

**What is tested:**
- Complete end-to-end sync
- Partial failure handling
- Logging
- Retry logic
- Processing order (FIFO)
- Resource filtering

### OfflineOperationsTest (11 tests)

Tests offline operations:

- ✅ `it_queues_create_operation`
- ✅ `it_queues_update_operation`
- ✅ `it_queues_delete_operation`
- ✅ `it_prevents_duplicate_queue_entries`
- ✅ `it_includes_timestamps_in_payload`
- ✅ `it_excludes_configured_fields_from_payload`
- ✅ `it_gets_only_pending_items`
- ✅ `it_filters_pending_items_by_resource`
- ✅ `it_purges_old_synced_items`
- ✅ `it_generates_unique_hash_for_each_operation`

**What is tested:**
- Queue for all 3 operations (create/update/delete)
- Deduplication
- Serialization with timestamps
- Field exclusion
- Filtering and purging

### ConflictResolutionTest (9 tests)

Tests conflict resolution under real conditions:

- ✅ `it_resolves_conflict_with_server_wins_strategy`
- ✅ `it_resolves_conflict_with_client_wins_strategy`
- ✅ `it_resolves_conflict_with_last_write_wins_strategy`
- ✅ `it_resolves_conflict_with_merge_strategy`
- ✅ `it_uses_per_resource_conflict_strategy`
- ✅ `it_dispatches_conflict_detected_event`
- ✅ `it_handles_conflicts_during_push_sync`
- ✅ `it_handles_multiple_conflicts_in_single_sync`

**What is tested:**
- All 4 strategies in action
- Per-resource configuration
- Conflict events
- Multiple conflicts

---

## 📊 Coverage

### Run coverage

```bash
composer test-coverage
```

Then open `coverage/html/index.html` in your browser.

### Target coverage

| Component | Target |
|-----------|--------|
| QueueManager | 90%+ |
| SyncEngine | 85%+ |
| ConflictResolver | 95%+ |
| Strategies | 100% |
| Models | 80%+ |
| Events | 100% |

---

## ✍️ Writing new tests

### Basic structure

```php
<?php

namespace Techparse\OfflineSync\Tests\Unit;

use Techparse\OfflineSync\Tests\TestCase;

class MyComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Common setup
    }

    /** @test */
    public function it_does_something()
    {
        // Arrange
        $expected = 'result';

        // Act
        $actual = $this->myComponent->doSomething();

        // Assert
        $this->assertEquals($expected, $actual);
    }
}
```

### Best Practices

1. **Descriptive naming**: `it_does_something_when_condition`
2. **One test = one concept**: test only one thing at a time
3. **AAA Pattern**: Arrange, Act, Assert
4. **Mock external dependencies**: HTTP, DB, etc.
5. **Isolate tests**: each test must be independent

### Test examples

#### Test with Mock

```php
/** @test */
public function it_calls_external_service()
{
    Http::fake([
        'https://api.example.com/*' => Http::response(['status' => 'ok'], 200)
    ]);

    $result = $this->service->callApi();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/endpoint';
    });
}
```

#### Test with Events

```php
/** @test */
public function it_dispatches_event()
{
    Event::fake([MyEvent::class]);

    $this->service->doSomething();

    Event::assertDispatched(MyEvent::class, function ($event) {
        return $event->data === 'expected';
    });
}
```

#### Test with Database

```php
/** @test */
public function it_creates_record()
{
    $data = ['name' => 'Test'];

    $this->service->create($data);

    $this->assertDatabaseHas('my_table', $data);
}
```

---

## 🐛 Debugging Tests

### Verbose output

```bash
vendor/bin/phpunit --verbose
```

### Stop on failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Specific test with debug

```bash
vendor/bin/phpunit --filter test_name --debug
```

### View SQL queries

In your test:
```php
\DB::enableQueryLog();
// ... your code
dd(\DB::getQueryLog());
```

---

## 📈 Test Statistics

**Total tests: 45**
- ✅ Unit tests: 27
- ✅ Feature tests: 18

**Components tested:**
- QueueManager
- SyncEngine
- ConflictResolver
- 4 Strategies
- Models (via tests)
- Events (via tests)
- Full sync flows

**Estimated run time:** ~5–10 seconds

---

## ⚡ CI/CD

Tests can be integrated into a CI/CD pipeline:

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test
      - name: Upload coverage
        run: composer test-coverage
```

---

## 🎯 Next tests to add

To reach 100% coverage:

1. ConnectivityService tests
2. Traits tests (Syncable)
3. Commands tests (Artisan)
4. Controller tests (SyncController)
5. Additional edge cases

---

**Need help?** Check the [main documentation](../README.md) or contact offlinessync@techparse.fr
