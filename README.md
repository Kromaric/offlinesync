# NativePHP Offline Sync & Backup

[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E10.0%7C%5E11.0-orange.svg)](https://laravel.com)
[![NativePHP Version](https://img.shields.io/badge/nativephp-%5E0.8.0-purple.svg)](https://nativephp.com)
[![Live Demo](https://img.shields.io/badge/demo-live-6366f1.svg)](https://offlinesync.techparse.fr)

**Offline-first synchronization plugin for NativePHP Mobile applications.**

Stop fighting with offline data. This plugin handles queuing, sync, and conflicts so you can focus on building features. Works out-of-the-box with zero native code required.

---

## ✨ Features

- ✅ **Automatic Queue Management** - Operations are automatically queued when offline
- ✅ **Bidirectional Sync** - Push local changes and pull remote updates
- ✅ **4 Conflict Resolution Strategies** - Server wins, Client wins, Last write wins, Merge
- ✅ **Auto-Connectivity Monitoring** - Syncs automatically when connection returns
- ✅ **Background Sync** - Works even when app is closed (iOS/Android)
- ✅ **Secure by Default** - HTTPS enforcement, auth-agnostic design (your app controls auth)
- ✅ **Observable** - Laravel events, logs, Artisan commands
- ✅ **Zero Native Code** - All native bridges included (Kotlin + Swift)

---

## 📋 Requirements

- **PHP** ≥ 8.1
- **Laravel** ≥ 10.0
- **NativePHP** ≥ 0.8.0
- **iOS** ≥ 14.0
- **Android** API Level ≥ 24 (Android 7.0)

---

## 🚀 Installation

### 1. Install via Composer

```bash
composer require techparse/offline-sync
```

### 2. Register the Plugin

```bash
php artisan native:plugin:register techparse/offline-sync
```

### 3. Publish Configuration

```bash
php artisan vendor:publish --tag=offline-sync-config
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Configure Your API

Edit `.env`:

```env
SYNC_API_URL=https://api.yourapp.com
SYNC_REQUIRE_HTTPS=true
```

---

## 📖 Quick Start

### 1. Add Syncable Trait to Your Models

```php
use Techparse\OfflineSync\Traits\Syncable;

class Task extends Model
{
    use Syncable;
    
    // Optional: customize sync behavior
    protected $syncResourceName = 'tasks';
    protected $syncExcluded = ['internal_notes'];
}
```

### 2. Map Resources in Config

Edit `config/offline-sync.php`:

```php
'resource_mapping' => [
    'tasks' => \App\Models\Task::class,
    'users' => \App\Models\User::class,
],
```

### 3. Use It!

```php
// Operations are automatically queued when offline
$task = Task::create(['title' => 'My Task']);

// Manual sync (optional)
use Techparse\OfflineSync\Facades\OfflineSync;

OfflineSync::sync(['tasks']);

// Check status
$status = OfflineSync::getStatus();
// ['pending_count' => 5, 'is_syncing' => false, 'last_sync' => '...']
```

---

## 🎯 Usage

### Automatic Syncing

With the `Syncable` trait, all create/update/delete operations are automatically queued:

```php
// These are automatically queued when offline
$task = Task::create(['title' => 'New Task']);
$task->update(['completed' => true]);
$task->delete();
```

### Manual Syncing

```php
use Techparse\OfflineSync\Facades\OfflineSync;

// Bidirectional sync (push + pull)
OfflineSync::sync(['tasks', 'users']);

// Push only (local → server)
OfflineSync::push(['tasks']);

// Pull only (server → local)
OfflineSync::pull(['users']);
```

### Queue Management

```php
// Get pending items
$pending = OfflineSync::getPending();
$pendingTasks = OfflineSync::getPending('tasks');

// Purge old synced items (older than 7 days)
OfflineSync::purgeOldItems(7);
```

### Artisan Commands

```bash
# Push local changes to server
php artisan sync:push

# Push specific resources
php artisan sync:push tasks users

# Pull remote changes
php artisan sync:pull tasks users

# Check queue status
php artisan sync:status

# Clear queue
php artisan sync:clear
php artisan sync:clear --failed
```

---

## ⚔️ Conflict Resolution

Configure in `config/offline-sync.php`:

```php
'conflict_resolution' => [
    // Default strategy for all resources
    'default_strategy' => 'server_wins',
    
    // Per-resource strategies
    'per_resource' => [
        'tasks' => 'last_write_wins',
        'users' => 'server_wins',
    ],
],
```

### Available Strategies

| Strategy | Description | Best For |
|----------|-------------|----------|
| **server_wins** | Server data always overwrites local | Critical data, auth |
| **client_wins** | Local data always overwrites server | User preferences |
| **last_write_wins** | Newest timestamp wins | Most use cases |
| **merge** | Intelligent field-level merge | Complex data |

---

## 🔐 Security

### Authentication

The plugin is **auth-agnostic** — it does not manage tokens or credentials. Your application is responsible for authentication. To forward an auth header on every sync request, set `offline-sync.security.headers` at runtime (e.g. in your `AppServiceProvider`):

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    $token = $this->app->make(Request::class)->bearerToken();

    if ($token) {
        config(['offline-sync.security.headers' => [
            'Authorization' => 'Bearer ' . $token,
        ]]);
    }
}
```

This works with any auth system: Laravel Sanctum, Passport, API keys, etc.

### HTTPS Enforcement

```env
SYNC_REQUIRE_HTTPS=true
```

---

## 📡 Backend Setup

### Routes

Add to `routes/api.php`:

```php
use Techparse\OfflineSync\Http\Controllers\SyncController;

Route::middleware('auth:sanctum')->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push']);
    Route::get('/pull/{resource}', [SyncController::class, 'pull']);
    Route::get('/status', [SyncController::class, 'status']);
    Route::get('/ping', [SyncController::class, 'ping']);
});
```

### Controller

Use the included `SyncController` or extend it for custom logic.

---

## 🔔 Events

Listen to sync events in your application:

```php
use Techparse\OfflineSync\Events\SyncCompleted;

Event::listen(SyncCompleted::class, function ($event) {
    Log::info("Synced {$event->synced} items in {$event->durationMs}ms");
});
```

### Available Events

- `SyncStarted` - Sync process started
- `SyncCompleted` - Sync finished successfully
- `SyncFailed` - Sync failed
- `ItemQueued` - Item added to queue
- `ItemSynced` - Item synchronized
- `ConflictDetected` - Conflict detected
- `QueuePurged` - Old items purged

---

## ⚙️ Configuration

See `config/offline-sync.php` for all options:

- API URL and security headers
- Resource mapping
- Conflict resolution strategies
- Connectivity settings
- Performance tuning
- Security options
- Logging configuration

---

## 📱 Native Platform Support

### Android (Kotlin)

- Automatic connectivity monitoring
- Background sync with WorkManager
- WiFi-only mode support
- Battery-aware scheduling

### iOS (Swift)

- Network framework monitoring
- Background fetch
- App refresh scheduling
- Low power mode respect

All native code is included. No manual native development required.

---

## 🧪 Testing

Run the test suite:

```bash
composer test
```

Or with coverage:

```bash
composer test-coverage
```

---

## 📚 Documentation

- [Installation Guide](docs/INSTALL.md)
- [Backend Setup](docs/BACKEND.md)
- [Conflict Resolution](docs/CONFLICTS.md)
- [Security Best Practices](docs/SECURITY.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)

---

## 🤝 Support

- **Email**: support@techparse.fr
- **Documentation**: https://docs.techparse.fr/offline-sync
- **Issues**: https://github.com/Kromaric/offlinesync/issues

---

## 📄 License

This software is open source, released under the MIT License. See [LICENSE](LICENSE) for details.


---

## 🙏 Credits

Built with ❤️ for the NativePHP community.

- [NativePHP](https://nativephp.com)
- [Laravel](https://laravel.com)

---

## 📝 Changelog

See [CHANGELOG.md](dev/CHANGELOG.md) for version history.

---

**Made by Techparse** | [Website](https://techparse.fr) | [Twitter](https://twitter.com/techparse)
