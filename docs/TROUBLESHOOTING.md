# Guide Dépannage & FAQ

Solutions aux problèmes courants et debugging d'OfflineSync.

---

## 📋 Table des matières

1. [Problèmes d'installation](#problèmes-dinstallation)
2. [Problèmes de synchronisation](#problèmes-de-synchronisation)
3. [Problèmes de connectivité](#problèmes-de-connectivité)
4. [Problèmes de conflits](#problèmes-de-conflits)
5. [Problèmes de performance](#problèmes-de-performance)
6. [Debugging avancé](#debugging-avancé)
7. [FAQ](#faq)

---

## 🔧 Problèmes d'Installation

### ❌ "Class OfflineSync not found"

**Cause :** Autoload non régénéré ou cache Laravel

**Solutions :**

```bash
# 1. Régénérer l'autoload
composer dump-autoload

# 2. Effacer les caches Laravel
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 3. Vérifier que le package est installé
composer show techparse/offline-sync
```

---

### ❌ Migrations échouent

**Erreur :** `SQLSTATE[42S01]: Base table or view already exists`

**Solutions :**

```bash
# Option 1 : Rollback et réessayer
php artisan migrate:rollback
php artisan migrate

# Option 2 : Fresh migration (⚠️ SUPPRIME LES DONNÉES)
php artisan migrate:fresh

# Option 3 : Vérifier le statut
php artisan migrate:status
```

**Erreur :** `SQLSTATE[HY000]: General error: 1 no such table`

**Solution :** Vérifier que SQLite est installé

```bash
php -m | grep sqlite
# Si absent, installer
sudo apt install php-sqlite3  # Ubuntu
brew install php              # macOS
```

---

### ❌ "Plugin not registered"

**Cause :** Plugin non enregistré dans NativePHP

**Solution :**

```bash
php artisan native:plugin:register techparse/offline-sync

# Vérifier l'enregistrement
php artisan native:plugin:list
```

---

## 🔄 Problèmes de Synchronisation

### ❌ Items ne se synchronisent pas

**Diagnostic :**

```bash
# 1. Vérifier la queue
php artisan sync:status

# 2. Essayer une sync manuelle
php artisan sync:push

# 3. Vérifier les logs
tail -f storage/logs/laravel.log
```

**Causes possibles :**

#### 1. Pas de connexion réseau

```php
use Techparse\OfflineSync\Facades\OfflineSync;

$status = OfflineSync::getStatus();
dd($status['is_online']); // false ?
```

**Solution :** Vérifier la connectivité

---

#### 2. URL API incorrecte

```env
# Vérifier .env
SYNC_API_URL=https://api.votre-app.com  # Correct ?
```

**Test :**

```bash
curl https://api.votre-app.com/api/sync/ping
# Devrait retourner {"status":"ok"}
```

---

#### 3. Token invalide

**Test :**

```bash
curl -H "Authorization: Bearer VOTRE_TOKEN" \
     https://api.votre-app.com/api/sync/status

# Devrait retourner 200, pas 401
```

**Solution :** Régénérer le token

```php
$user = User::find(1);
$token = $user->createToken('mobile-app')->plainTextToken;
// Mettre à jour le token dans l'app
```

---

#### 4. Items marqués comme failed

**Diagnostic :**

```php
$failed = SyncQueueItem::where('status', 'failed')->get();
foreach ($failed as $item) {
    dump($item->error_message);
}
```

**Solution :** Corriger l'erreur et réessayer

```php
$item->update(['status' => 'pending', 'retry_count' => 0]);
```

---

### ❌ Sync très lente

**Diagnostic :**

```php
$start = microtime(true);
OfflineSync::sync();
$duration = (microtime(true) - $start) * 1000;
echo "Durée: {$duration}ms";
```

**Causes possibles :**

#### 1. Trop d'items en queue

```php
$pending = OfflineSync::getPending();
echo "Items en attente: " . $pending->count();
```

**Solution :** Réduire le batch size ou purger

```env
SYNC_BATCH_SIZE=25  # Au lieu de 50
```

```php
OfflineSync::purgeOldItems(3); // Purger après 3 jours
```

---

#### 2. Connexion lente

**Test :**

```bash
curl -w "@curl-format.txt" -o /dev/null -s \
     "https://api.votre-app.com/api/sync/ping"

# curl-format.txt :
# time_total: %{time_total}s
# time_connect: %{time_connect}s
```

**Solution :** Optimiser le serveur ou utiliser un CDN

---

#### 3. Backend lent

**Backend logging :**

```php
$start = microtime(true);
// ... traitement sync
Log::info('Sync duration', ['ms' => (microtime(true) - $start) * 1000]);
```

**Solution :** Optimiser les queries, ajouter des index

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->index(['user_id', 'updated_at']);
});
```

---

## 🌐 Problèmes de Connectivité

### ❌ "No internet connection" en permanence

**Diagnostic Android :**

```kotlin
val monitor = ConnectivityMonitor(context)
Log.d("Sync", "Online: ${monitor.isOnline()}")
Log.d("Sync", "Type: ${monitor.getConnectionType()}")
```

**Diagnostic iOS :**

```swift
let monitor = ConnectivityMonitor()
print("Online: \(monitor.isOnline())")
print("Type: \(monitor.getConnectionType())")
```

**Causes possibles :**

#### 1. Permissions manquantes (Android)

**Vérifier AndroidManifest.xml :**

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
```

#### 2. Permissions manquantes (iOS)

**Vérifier Info.plist :**

```xml
<key>NSLocalNetworkUsageDescription</key>
<string>Cette app a besoin d'accéder au réseau</string>
```

---

### ❌ "HTTPS required" en développement local

**Solution temporaire (DEV uniquement) :**

```env
SYNC_REQUIRE_HTTPS=false
```

**⚠️ EN PRODUCTION, TOUJOURS :**

```env
SYNC_REQUIRE_HTTPS=true
```

---

## ⚔️ Problèmes de Conflits

### ❌ Trop de conflits

**Diagnostic :**

```php
$logs = SyncLog::recent(7)->get();
$totalConflicts = $logs->sum('conflicts_count');
echo "Conflits (7 jours): {$totalConflicts}";
```

**Causes possibles :**

#### 1. Mauvaise stratégie configurée

**Solution :** Utiliser `last_write_wins`

```php
'per_resource' => [
    'tasks' => 'last_write_wins', // Au lieu de server_wins
],
```

#### 2. Timestamps désynchronisés

**Test :**

```php
echo "Serveur: " . now()->toIso8601String() . "\n";
echo "Mobile: " . $mobileTimestamp . "\n";
```

**Solution :** Synchroniser l'horloge

```bash
# Linux
sudo ntpdate pool.ntp.org

# Windows
w32tm /resync
```

---

### ❌ Conflits perdent des données

**Cause :** Stratégie `server_wins` ou `client_wins` trop stricte

**Solution :** Utiliser `merge` ou `last_write_wins`

```php
'per_resource' => [
    'tasks' => 'merge', // Fusion intelligente
],
```

---

## 🐌 Problèmes de Performance

### ❌ Queue devient énorme

**Diagnostic :**

```php
$queueSize = SyncQueueItem::count();
echo "Taille queue: {$queueSize}";
```

**Solutions :**

#### 1. Purge automatique

```php
// Ajouter au scheduler
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        OfflineSync::purgeOldItems(7);
    })->daily();
}
```

#### 2. Limite de taille

```env
SYNC_MAX_QUEUE=1000
```

```php
if (SyncQueueItem::count() >= config('offline-sync.performance.max_queue_size')) {
    // Arrêter de queuer ou purger les plus anciens
    SyncQueueItem::oldest()->limit(100)->delete();
}
```

---

### ❌ Sync consomme trop de mémoire

**Diagnostic :**

```php
echo "Mémoire: " . memory_get_usage(true) / 1024 / 1024 . "MB\n";
```

**Solutions :**

#### 1. Réduire le batch size

```env
SYNC_BATCH_SIZE=25
```

#### 2. Chunking

```php
// Dans SyncEngine
SyncQueueItem::pending()->chunk(50, function ($items) {
    $this->processItems($items);
});
```

---

## 🔍 Debugging Avancé

### Activer le mode debug

**Laravel :**

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

**OfflineSync :**

```env
SYNC_LOGGING=true
SYNC_LOG_CHANNEL=daily
```

### Logs détaillés

```php
use Illuminate\Support\Facades\Log;

Log::channel('sync')->debug('Sync started', [
    'user_id' => $user->id,
    'resources' => $resources,
]);

Log::channel('sync')->debug('Item processed', [
    'item_id' => $item->id,
    'operation' => $item->operation,
]);
```

### Tracer les requêtes HTTP

**Laravel Telescope :**

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Accéder à `http://localhost/telescope` pour voir toutes les requêtes.

### Profiling

```php
$start = microtime(true);

// Code à profiler
OfflineSync::sync();

$duration = microtime(true) - $start;
Log::info('Performance', [
    'duration' => $duration,
    'memory' => memory_get_peak_usage(true),
]);
```

---

## ❓ FAQ

### Q1 : Combien de temps les items restent en queue ?

**R :** Jusqu'à ce qu'ils soient synchronisés ou purgés.

**Configuration :**

```env
SYNC_PURGE_DAYS=7  # Purger après 7 jours
```

---

### Q2 : Que se passe-t-il si l'app se ferme pendant une sync ?

**R :** La sync reprend au prochain démarrage. Les items sont persistés dans SQLite.

---

### Q3 : Peut-on sync en arrière-plan ?

**R :** Oui, via WorkManager (Android) et Background Tasks (iOS).

**Configuration :**

```env
SYNC_BACKGROUND=true
SYNC_CHECK_INTERVAL=30  # Minutes
```

---

### Q4 : Comment tester sans backend ?

**R :** Utiliser des mocks HTTP :

```php
Http::fake([
    '*/sync/push' => Http::response(['success' => true, 'synced' => 1]),
    '*/sync/pull/*' => Http::response(['data' => []]),
]);

OfflineSync::sync();
```

---

### Q5 : Les données sync sont-elles chiffrées en transit ?

**R :** Oui, via HTTPS (`SYNC_REQUIRE_HTTPS=true`). Le transport est chiffré TLS. Les données au repos dans la queue locale ne sont pas chiffrées par le plugin — c'est à votre app de gérer le chiffrement local si nécessaire.

---

### Q6 : Peut-on sync en WiFi uniquement ?

**R :** Oui :

```env
SYNC_REQUIRE_WIFI=true
```

---

### Q7 : Comment forcer une resynchronisation complète ?

**R :**

```php
// Supprimer toute la queue
SyncQueueItem::truncate();

// Re-queue tous les modèles
Task::chunk(100, function ($tasks) {
    foreach ($tasks as $task) {
        OfflineSync::queue($task, 'update');
    }
});

// Sync
OfflineSync::sync();
```

---

### Q8 : Les timestamps doivent-ils être en UTC ?

**R :** Oui, ISO8601 UTC est recommandé.

```php
$timestamp = now()->timezone('UTC')->toIso8601String();
```

---

### Q9 : Comment sync uniquement certains modèles ?

**R :**

```php
OfflineSync::sync(['tasks', 'projects']); // Seulement ces ressources
```

---

### Q10 : Peut-on désactiver complètement la sync ?

**R :** Oui :

```env
SYNC_AUTO_SYNC=false
```

Ou retirer le trait `Syncable` des modèles.

---

## 🛠️ Outils de Diagnostic

### Script de diagnostic complet

```php
<?php

// diagnostic.php
use Techparse\OfflineSync\Facades\OfflineSync;
use Techparse\OfflineSync\Models\SyncQueueItem;
use Techparse\OfflineSync\Models\SyncLog;

echo "=== DIAGNOSTIC OFFLINESYNC ===\n\n";

// 1. Configuration
echo "Configuration:\n";
echo "  API URL: " . config('offline-sync.api_url') . "\n";
echo "  HTTPS: " . (config('offline-sync.security.require_https') ? 'Oui' : 'Non') . "\n";
$headers = array_keys(config('offline-sync.security.headers', []));
echo "  Headers: " . (count($headers) ? implode(', ', $headers) : 'aucun') . "\n\n";

// 2. Queue
echo "Queue:\n";
$pending = SyncQueueItem::where('status', 'pending')->count();
$failed = SyncQueueItem::where('status', 'failed')->count();
$synced = SyncQueueItem::where('status', 'synced')->count();
echo "  Pending: {$pending}\n";
echo "  Failed: {$failed}\n";
echo "  Synced: {$synced}\n\n";

// 3. Logs
echo "Logs (7 derniers jours):\n";
$stats = SyncLog::getStats(7);
echo "  Total syncs: {$stats['total_syncs']}\n";
echo "  Succès: {$stats['successful_syncs']}\n";
echo "  Échecs: {$stats['failed_syncs']}\n";
echo "  Conflits: {$stats['total_conflicts']}\n";
echo "  Durée moyenne: {$stats['avg_duration_ms']}ms\n\n";

// 4. Connectivité
echo "Connectivité:\n";
try {
    $response = Http::timeout(5)->get(config('offline-sync.api_url') . '/api/sync/ping');
    echo "  Serveur: " . ($response->successful() ? '✅ OK' : '❌ ERREUR') . "\n";
} catch (\Exception $e) {
    echo "  Serveur: ❌ INJOIGNABLE\n";
    echo "  Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";
```

**Exécution :**

```bash
php artisan tinker
>>> include 'diagnostic.php';
```

---

## 📞 Obtenir de l'aide

### Informations à fournir

Lors d'une demande de support, incluez :

1. **Version du plugin** : `composer show techparse/offline-sync`
2. **Version PHP** : `php --version`
3. **Version Laravel** : `php artisan --version`
4. **Logs** : `storage/logs/laravel.log` (dernières 50 lignes)
5. **Configuration** : `.env` (sans les tokens !)
6. **Steps to reproduce** : Comment reproduire le problème

### Canaux de support

- 📧 **Email** : support@techparse.fr
- 📖 **Documentation** : https://docs.techparse.fr
- 🐛 **Issues GitHub** : https://github.com/Kromaric/offlinesync/issues

### Temps de réponse

- Support email : 24-48h
- Issues critiques : 12-24h
- Questions générales : 48-72h

---

**Problème résolu ? Partagez votre solution !** 🎉
