# Troubleshooting & FAQ

Solutions to common problems and debugging tips for OfflineSync.

---

## 📋 Table of contents

1. [Installation issues](#installation-issues)
2. [Sync issues](#sync-issues)
3. [Connectivity issues](#connectivity-issues)
4. [Conflict issues](#conflict-issues)
5. [Performance issues](#performance-issues)
6. [Advanced debugging](#advanced-debugging)
7. [FAQ](#faq)

---

## 🔧 Installation Issues

### ❌ "Class OfflineSync not found"

**Cause:** Autoload not regenerated or Laravel cache stale

**Solutions:**

```bash
# 1. Regenerate autoload
composer dump-autoload

# 2. Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 3. Verify the package is installed
composer show techparse/offline-sync
```

---

### ❌ Migrations fail

**Error:** `SQLSTATE[42S01]: Base table or view already exists`

**Solutions:**

```bash
# Option 1: Rollback and retry
php artisan migrate:rollback
php artisan migrate

# Option 2: Fresh migration (⚠️ DELETES ALL DATA)
php artisan migrate:fresh

# Option 3: Check status
php artisan migrate:status
```

**Error:** `SQLSTATE[HY000]: General error: 1 no such table`

**Solution:** Verify SQLite is installed

```bash
php -m | grep sqlite
# If missing, install it
sudo apt install php-sqlite3  # Ubuntu
brew install php              # macOS
```

---

### ❌ "Plugin not registered"

**Cause:** Plugin not registered with NativePHP

**Solution:**

```bash
php artisan native:plugin:register techparse/offline-sync

# Verify registration
php artisan native:plugin:list
```

---

## 🔄 Sync Issues

### ❌ Items not syncing

**Diagnose:**

```bash
# 1. Check the queue
php artisan sync:status

# 2. Try a manual sync
php artisan sync:push

# 3. Check logs
tail -f storage/logs/laravel.log
```

**Possible causes:**

#### 1. No network connection

```php
use Techparse\OfflineSync\Facades\OfflineSync;

$status = OfflineSync::getStatus();
dd($status['is_online']); // false?
```

**Solution:** Check connectivity

---

#### 2. Incorrect API URL

```env
# Check .env
SYNC_API_URL=https://api.your-app.com  # Correct?
```

**Test:**

```bash
curl https://api.your-app.com/api/sync/ping
# Should return {"status":"ok"}
```

---

#### 3. Invalid token

**Test:**

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://api.your-app.com/api/sync/status

# Should return 200, not 401
```

**Solution:** Regenerate the token

```php
$user  = User::find(1);
$token = $user->createToken('mobile-app')->plainTextToken;
// Update the token in the app
```

---

#### 4. Items marked as failed

**Diagnose:**

```php
$failed = SyncQueueItem::where('status', 'failed')->get();
foreach ($failed as $item) {
    dump($item->error_message);
}
```

**Solution:** Fix the error and retry

```php
$item->update(['status' => 'pending', 'retry_count' => 0]);
```

---

### ❌ Sync is very slow

**Diagnose:**

```php
$start    = microtime(true);
OfflineSync::sync();
$duration = (microtime(true) - $start) * 1000;
echo "Duration: {$duration}ms";
```

**Possible causes:**

#### 1. Too many items in queue

```php
$pending = OfflineSync::getPending();
echo "Pending items: " . $pending->count();
```

**Solution:** Reduce batch size or purge

```env
SYNC_BATCH_SIZE=25  # Instead of 50
```

```php
OfflineSync::purgeOldItems(3); // Purge after 3 days
```

---

#### 2. Slow connection

**Test:**

```bash
curl -w "@curl-format.txt" -o /dev/null -s \
     "https://api.your-app.com/api/sync/ping"

# curl-format.txt:
# time_total:   %{time_total}s
# time_connect: %{time_connect}s
```

**Solution:** Optimise the server or use a CDN

---

#### 3. Slow backend

**Backend logging:**

```php
$start = microtime(true);
// ... sync processing
Log::info('Sync duration', ['ms' => (microtime(true) - $start) * 1000]);
```

**Solution:** Optimise queries, add indexes

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->index(['user_id', 'updated_at']);
});
```

---

## 🌐 Connectivity Issues

### ❌ "No internet connection" all the time

**Diagnose on Android:**

```kotlin
val monitor = ConnectivityMonitor(context)
Log.d("Sync", "Online: ${monitor.isOnline()}")
Log.d("Sync", "Type: ${monitor.getConnectionType()}")
```

**Diagnose on iOS:**

```swift
let monitor = ConnectivityMonitor()
print("Online: \(monitor.isOnline())")
print("Type: \(monitor.getConnectionType())")
```

**Possible causes:**

#### 1. Missing permissions (Android)

**Check AndroidManifest.xml:**

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
```

#### 2. Missing permissions (iOS)

**Check Info.plist:**

```xml
<key>NSLocalNetworkUsageDescription</key>
<string>This app needs network access to synchronize your data.</string>
```

---

### ❌ "HTTPS required" in local development

**Temporary fix (DEV only):**

```env
SYNC_REQUIRE_HTTPS=false
```

**⚠️ IN PRODUCTION, ALWAYS:**

```env
SYNC_REQUIRE_HTTPS=true
```

---

## ⚔️ Conflict Issues

### ❌ Too many conflicts

**Diagnose:**

```php
$logs           = SyncLog::recent(7)->get();
$totalConflicts = $logs->sum('conflicts_count');
echo "Conflicts (7 days): {$totalConflicts}";
```

**Possible causes:**

#### 1. Wrong strategy configured

**Solution:** Use `last_write_wins`

```php
'per_resource' => [
    'tasks' => 'last_write_wins', // Instead of server_wins
],
```

#### 2. Out-of-sync timestamps

**Test:**

```php
echo "Server: " . now()->toIso8601String() . "\n";
echo "Mobile: " . $mobileTimestamp . "\n";
```

**Solution:** Sync the clock

```bash
# Linux
sudo ntpdate pool.ntp.org

# Windows
w32tm /resync
```

---

### ❌ Conflicts lose data

**Cause:** `server_wins` or `client_wins` strategy too strict

**Solution:** Use `merge` or `last_write_wins`

```php
'per_resource' => [
    'tasks' => 'merge', // Smart field-level merge
],
```

---

## 🐌 Performance Issues

### ❌ Queue grows very large

**Diagnose:**

```php
$queueSize = SyncQueueItem::count();
echo "Queue size: {$queueSize}";
```

**Solutions:**

#### 1. Automatic purge

```php
// Add to scheduler in app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        OfflineSync::purgeOldItems(7);
    })->daily();
}
```

#### 2. Size limit

```env
SYNC_MAX_QUEUE=1000
```

```php
if (SyncQueueItem::count() >= config('offline-sync.performance.max_queue_size')) {
    // Stop queuing or purge oldest
    SyncQueueItem::oldest()->limit(100)->delete();
}
```

---

### ❌ Sync uses too much memory

**Diagnose:**

```php
echo "Memory: " . memory_get_usage(true) / 1024 / 1024 . "MB\n";
```

**Solutions:**

#### 1. Reduce batch size

```env
SYNC_BATCH_SIZE=25
```

#### 2. Chunking

```php
// In SyncEngine
SyncQueueItem::pending()->chunk(50, function ($items) {
    $this->processItems($items);
});
```

---

## 🔍 Advanced Debugging

### Enable debug mode

**Laravel:**

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

**OfflineSync:**

```env
SYNC_LOGGING=true
SYNC_LOG_CHANNEL=daily
```

### Detailed logs

```php
use Illuminate\Support\Facades\Log;

Log::channel('sync')->debug('Sync started', [
    'user_id'   => $user->id,
    'resources' => $resources,
]);

Log::channel('sync')->debug('Item processed', [
    'item_id'   => $item->id,
    'operation' => $item->operation,
]);
```

### Trace HTTP requests

**Laravel Telescope:**

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Visit `http://localhost/telescope` to see all requests.

### Profiling

```php
$start = microtime(true);

// Code to profile
OfflineSync::sync();

$duration = microtime(true) - $start;
Log::info('Performance', [
    'duration' => $duration,
    'memory'   => memory_get_peak_usage(true),
]);
```

---

## ❓ FAQ

### Q1: How long do items stay in the queue?

**A:** Until they are synchronised or purged.

**Configuration:**

```env
SYNC_PURGE_DAYS=7  # Purge after 7 days
```

---

### Q2: What happens if the app closes during a sync?

**A:** The sync resumes on next launch. Items are persisted in SQLite.

---

### Q3: Can sync run in the background?

**A:** Yes, via WorkManager (Android) and Background Tasks (iOS).

**Configuration:**

```env
SYNC_BACKGROUND=true
SYNC_CHECK_INTERVAL=30  # Minutes
```

---

### Q4: How to test without a backend?

**A:** Use HTTP mocks:

```php
Http::fake([
    '*/sync/push'   => Http::response(['success' => true, 'synced' => 1]),
    '*/sync/pull/*' => Http::response(['data' => []]),
]);

OfflineSync::sync();
```

---

### Q5: Is synced data encrypted in transit?

**A:** Yes, via HTTPS (`SYNC_REQUIRE_HTTPS=true`). Transport is TLS-encrypted. Data at rest in the local queue is not encrypted by the plugin — your app is responsible for local encryption if needed.

---

### Q6: Can sync be restricted to Wi-Fi only?

**A:** Yes:

```env
SYNC_REQUIRE_WIFI=true
```

---

### Q7: How to force a full resync?

**A:**

```php
// Clear the entire queue
SyncQueueItem::truncate();

// Re-queue all models
Task::chunk(100, function ($tasks) {
    foreach ($tasks as $task) {
        OfflineSync::queue($task, 'update');
    }
});

// Sync
OfflineSync::sync();
```

---

### Q8: Should timestamps be in UTC?

**A:** Yes, ISO8601 UTC is recommended.

```php
$timestamp = now()->timezone('UTC')->toIso8601String();
```

---

### Q9: How to sync only specific models?

**A:**

```php
OfflineSync::sync(['tasks', 'projects']); // Only these resources
```

---

### Q10: Can sync be disabled completely?

**A:** Yes:

```env
SYNC_AUTO_SYNC=false
```

Or remove the `Syncable` trait from your models.

---

## 🛠️ Diagnostic Tools

### Full diagnostic script

```php
<?php

// diagnostic.php
use Techparse\OfflineSync\Facades\OfflineSync;
use Techparse\OfflineSync\Models\SyncQueueItem;
use Techparse\OfflineSync\Models\SyncLog;

echo "=== OFFLINESYNC DIAGNOSTIC ===\n\n";

// 1. Configuration
echo "Configuration:\n";
echo "  API URL: " . config('offline-sync.api_url') . "\n";
echo "  HTTPS:   " . (config('offline-sync.security.require_https') ? 'Yes' : 'No') . "\n";
$headers = array_keys(config('offline-sync.security.headers', []));
echo "  Headers: " . (count($headers) ? implode(', ', $headers) : 'none') . "\n\n";

// 2. Queue
echo "Queue:\n";
$pending = SyncQueueItem::where('status', 'pending')->count();
$failed  = SyncQueueItem::where('status', 'failed')->count();
$synced  = SyncQueueItem::where('status', 'synced')->count();
echo "  Pending: {$pending}\n";
echo "  Failed:  {$failed}\n";
echo "  Synced:  {$synced}\n\n";

// 3. Logs
echo "Logs (last 7 days):\n";
$stats = SyncLog::getStats(7);
echo "  Total syncs:     {$stats['total_syncs']}\n";
echo "  Successful:      {$stats['successful_syncs']}\n";
echo "  Failed:          {$stats['failed_syncs']}\n";
echo "  Conflicts:       {$stats['total_conflicts']}\n";
echo "  Avg duration:    {$stats['avg_duration_ms']}ms\n\n";

// 4. Connectivity
echo "Connectivity:\n";
try {
    $response = Http::timeout(5)->get(config('offline-sync.api_url') . '/api/sync/ping');
    echo "  Server: " . ($response->successful() ? '✅ OK' : '❌ ERROR') . "\n";
} catch (\Exception $e) {
    echo "  Server: ❌ UNREACHABLE\n";
    echo "  Error:  " . $e->getMessage() . "\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
```

**Run it:**

```bash
php artisan tinker
>>> include 'diagnostic.php';
```

---

## 📞 Getting help

### Information to provide

When requesting support, include:

1. **Plugin version**: `composer show techparse/offline-sync`
2. **PHP version**: `php --version`
3. **Laravel version**: `php artisan --version`
4. **Logs**: `storage/logs/laravel.log` (last 50 lines)
5. **Configuration**: `.env` (without tokens!)
6. **Steps to reproduce**: how to reproduce the issue

### Support channels

- 📧 **Email**: offlinessync@techparse.fr
- 📖 **Documentation**: https://docs.offlinesync.techparse.fr
- 🐛 **GitHub Issues**: https://github.com/Kromaric/offlinesync/issues

### Response times

- Email support: 24–48 h
- Critical issues: 12–24 h
- General questions: 48–72 h

---

**Problem solved? Share your solution!** 🎉
