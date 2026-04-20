# Tests Documentation

Guide complet pour les tests du plugin NativePHP Offline Sync & Backup.

## 📋 Table des matières

1. [Structure des tests](#structure-des-tests)
2. [Exécution des tests](#exécution-des-tests)
3. [Tests unitaires](#tests-unitaires)
4. [Tests d'intégration](#tests-dintégration)
5. [Coverage](#coverage)
6. [Écrire de nouveaux tests](#écrire-de-nouveaux-tests)

---

## 🗂️ Structure des tests

```
tests/
├── TestCase.php              # Classe de base pour tous les tests
├── Unit/                     # Tests unitaires (composants isolés)
│   ├── QueueManagerTest.php
│   ├── ConflictResolverTest.php
│   ├── StrategiesTest.php
│   └── SyncEngineTest.php
└── Feature/                  # Tests d'intégration (flux complets)
    ├── SyncFlowTest.php
    ├── OfflineOperationsTest.php
    └── ConflictResolutionTest.php
```

**Total : 8 fichiers de tests**

---

## 🚀 Exécution des tests

### Tous les tests

```bash
composer test
# ou
vendor/bin/phpunit
```

### Tests unitaires uniquement

```bash
composer test-unit
# ou
vendor/bin/phpunit --testsuite Unit
```

### Tests d'intégration uniquement

```bash
composer test-feature
# ou
vendor/bin/phpunit --testsuite Feature
```

### Test spécifique

```bash
composer test-filter -- NomDuTest
# ou
vendor/bin/phpunit --filter test_method_name
```

### Avec coverage HTML

```bash
composer test-coverage
```

Les résultats seront dans `coverage/html/index.html`

---

## 🧪 Tests Unitaires

### QueueManagerTest (2 tests)

Teste la gestion de la file d'attente :

- ✅ `it_gets_pending_items` - Récupération des items en attente
- ✅ `it_purges_old_synced_items` - Purge des anciens items

**Ce qui est testé :**
- Filtrage par statut (pending vs synced)
- Purge basée sur la date
- Comptage des items

### ConflictResolverTest (5 tests)

Teste la résolution de conflits :

- ✅ `it_uses_default_strategy_when_no_specific_strategy_configured`
- ✅ `it_uses_per_resource_strategy_when_configured`
- ✅ `it_dispatches_conflict_detected_event`
- ✅ `it_falls_back_to_server_wins_for_unknown_strategy`
- ✅ `it_resolves_conflicts_for_multiple_resources`

**Ce qui est testé :**
- Stratégie par défaut
- Stratégies par ressource
- Fallback sur stratégie inconnue
- Events Laravel

### StrategiesTest (10 tests)

Teste les 4 stratégies de résolution :

**ServerWins :**
- ✅ `server_wins_strategy_always_returns_remote_data`

**ClientWins :**
- ✅ `client_wins_strategy_always_returns_local_data`

**LastWriteWins :**
- ✅ `last_write_wins_strategy_returns_newer_data_based_on_timestamp`
- ✅ `last_write_wins_prefers_remote_when_timestamps_are_equal`

**Merge :**
- ✅ `merge_strategy_combines_non_null_values`
- ✅ `merge_strategy_handles_empty_local_data`
- ✅ `merge_strategy_handles_empty_remote_data`
- ✅ `merge_strategy_preserves_all_data_when_no_conflicts`

**Interface :**
- ✅ `all_strategies_implement_required_interface`

### SyncEngineTest (10 tests)

Teste le moteur de synchronisation :

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

**Ce qui est testé :**
- Gestion de la connectivité
- Events (started, completed, failed)
- Logs de synchronisation
- Sécurité HTTPS
- Batching des items

---

## 🔄 Tests d'Intégration

### SyncFlowTest (9 tests)

Teste les flux de synchronisation complets :

- ✅ `it_completes_full_sync_flow_successfully`
- ✅ `it_handles_partial_sync_failures`
- ✅ `it_creates_sync_log_for_each_sync_operation`
- ✅ `it_retries_failed_items_up_to_max_attempts`
- ✅ `it_processes_queue_in_correct_order`
- ✅ `it_handles_empty_queue_gracefully`
- ✅ `it_supports_resource_filtering`

**Ce qui est testé :**
- Sync complète end-to-end
- Gestion des échecs partiels
- Logging
- Retry logic
- Ordre de traitement (FIFO)
- Filtrage par ressource

### OfflineOperationsTest (11 tests)

Teste les opérations offline :

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

**Ce qui est testé :**
- Queue des 3 opérations (create/update/delete)
- Déduplication
- Serialization avec timestamps
- Exclusion de champs
- Filtrage et purge

### ConflictResolutionTest (9 tests)

Teste la résolution de conflits en conditions réelles :

- ✅ `it_resolves_conflict_with_server_wins_strategy`
- ✅ `it_resolves_conflict_with_client_wins_strategy`
- ✅ `it_resolves_conflict_with_last_write_wins_strategy`
- ✅ `it_resolves_conflict_with_merge_strategy`
- ✅ `it_uses_per_resource_conflict_strategy`
- ✅ `it_dispatches_conflict_detected_event`
- ✅ `it_handles_conflicts_during_push_sync`
- ✅ `it_handles_multiple_conflicts_in_single_sync`

**Ce qui est testé :**
- Les 4 stratégies en action
- Configuration per-resource
- Events de conflit
- Conflits multiples

---

## 📊 Coverage

### Exécuter le coverage

```bash
composer test-coverage
```

Ouvre ensuite `coverage/html/index.html` dans ton navigateur.

### Coverage attendu

| Composant | Coverage cible |
|-----------|----------------|
| QueueManager | 90%+ |
| SyncEngine | 85%+ |
| ConflictResolver | 95%+ |
| Strategies | 100% |
| Models | 80%+ |
| Events | 100% |

---

## ✍️ Écrire de nouveaux tests

### Structure de base

```php
<?php

namespace VendorName\OfflineSync\Tests\Unit;

use VendorName\OfflineSync\Tests\TestCase;

class MyComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup commun
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

1. **Nommage descriptif** : `it_does_something_when_condition`
2. **Un test = un concept** : Ne teste qu'une chose à la fois
3. **AAA Pattern** : Arrange, Act, Assert
4. **Mock les dépendances externes** : HTTP, DB, etc.
5. **Isoler les tests** : Chaque test doit être indépendant

### Exemples de tests

#### Test avec Mock

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

#### Test avec Events

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

#### Test avec Database

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

### Verbose Output

```bash
vendor/bin/phpunit --verbose
```

### Stop on Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Test spécifique avec debug

```bash
vendor/bin/phpunit --filter test_name --debug
```

### Voir les queries SQL

Dans ton test :
```php
\DB::enableQueryLog();
// ... ton code
dd(\DB::getQueryLog());
```

---

## 📈 Statistiques Tests

**Total tests : 45**
- ✅ Tests Unitaires : 27
- ✅ Tests Feature : 18

**Composants testés :**
- QueueManager
- SyncEngine
- ConflictResolver
- 4 Strategies
- Models (via tests)
- Events (via tests)
- Full sync flows

**Temps d'exécution estimé :** ~5-10 secondes

---

## ⚡ CI/CD

Les tests peuvent être intégrés dans un pipeline CI/CD :

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

## 🎯 Prochains tests à ajouter

Pour atteindre 100% de coverage :

1. ConnectivityService tests
2. Traits tests (Syncable)
3. Commands tests (Artisan)
4. Controller tests (SyncController)
5. Edge cases supplémentaires

---

**Besoin d'aide ?** Consulte la [documentation principale](../README.md) ou contacte support@vendorname.com
