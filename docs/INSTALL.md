# Installation Guide - NativePHP Offline Sync

Complete guide to install and configure the OfflineSync plugin in your NativePHP Mobile application.

---

## 📋 Requirements

### Development environment

- ✅ **PHP** ≥ 8.1
- ✅ **Composer** (latest version)
- ✅ **Laravel** ≥ 10.0
- ✅ **NativePHP** ≥ 0.8.0 installed
- ✅ **SQLite** (bundled with PHP)

### Mobile development

#### Android
- ✅ Android Studio (latest version)
- ✅ Android SDK API Level ≥ 24
- ✅ Kotlin 1.9.0+
- ✅ Gradle 8.0+

#### iOS
- ✅ Xcode 15.0+
- ✅ iOS 14.0+ (simulator or device)
- ✅ CocoaPods or Swift Package Manager
- ✅ macOS (required for iOS development)

### Backend API
- ✅ Laravel server accessible via HTTPS
- ✅ Database (MySQL, PostgreSQL, etc.)
- ✅ Laravel Sanctum (for authentication)

---

## 🚀 Installation

### Step 1 — Install the plugin

```bash
composer require techparse/offline-sync
```

Verify:

```bash
composer show techparse/offline-sync
```

---

### Step 2 — Register the plugin

```bash
php artisan native:plugin:register techparse/offline-sync
```

This command:
- ✅ Registers the plugin with NativePHP
- ✅ Copies the native bridges (Kotlin / Swift)
- ✅ Configures permissions

---

### Step 3 — Publish the configuration

```bash
php artisan vendor:publish --tag=offline-sync-config
```

This creates `config/offline-sync.php`.

---

### Step 4 — Basic configuration

Edit your `.env`:

```env
# Backend API URL
SYNC_API_URL=https://api.your-app.com

# Security
SYNC_REQUIRE_HTTPS=true

# Performance
SYNC_BATCH_SIZE=50
SYNC_MAX_QUEUE=1000

# Connectivity
SYNC_AUTO_SYNC=true
SYNC_REQUIRE_WIFI=false

# Retry
SYNC_MAX_RETRIES=3
SYNC_RETRY_DELAY=60

# Logging
SYNC_LOGGING=true
SYNC_LOG_CHANNEL=daily
```

---

### Step 5 — Run migrations

```bash
php artisan migrate
```

This creates:
- ✅ `offline_sync_queue` — synchronization queue
- ✅ `offline_sync_logs` — sync history

Verify:

```bash
php artisan migrate:status
```

Expected output:
```
Ran? Migration
Yes  2025_01_01_000001_create_offline_sync_queue_table
Yes  2025_01_01_000002_create_offline_sync_logs_table
```

---

### Step 6 — Configure your models

Add the `Syncable` trait to your Eloquent models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Techparse\OfflineSync\Traits\Syncable;

class Task extends Model
{
    use Syncable;

    protected $fillable = ['title', 'description', 'completed'];

    // Optional: customize the resource name
    protected $syncResourceName = 'tasks';

    // Optional: exclude fields from sync
    protected $syncExcluded = ['internal_notes', 'admin_only'];
}
```

---

### Step 7 — Map resources

Edit `config/offline-sync.php`:

```php
'resource_mapping' => [
    'tasks'    => \App\Models\Task::class,
    'users'    => \App\Models\User::class,
    'projects' => \App\Models\Project::class,
    // Add your other models...
],
```

---

### Step 8 — Configure conflict resolution

```php
'conflict_resolution' => [
    // Default strategy for all resources
    'default_strategy' => 'last_write_wins',

    // Per-resource strategies
    'per_resource' => [
        'tasks'    => 'last_write_wins',
        'users'    => 'server_wins',    // Server user data takes priority
        'settings' => 'client_wins',   // Local settings take priority
        'notes'    => 'merge',         // Smart field-level merge
    ],
],
```

**Available strategies:**
- `server_wins` — server always wins
- `client_wins` — client always wins
- `last_write_wins` — newest timestamp wins
- `merge` — smart field-level merge

---

## 🔧 Backend Setup

### Step 9 — Install Sanctum (if not already done)

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### Step 10 — Configure API routes

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

### Step 11 — Token generation

In your login controller:

```php
$token = $user->createToken('mobile-app')->plainTextToken;

return response()->json([
    'token' => $token,
    'user'  => $user,
]);
```

### Step 12 — Inject the token into plugin headers

In `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Http\Request;

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

---

## 📱 Mobile Setup

### Android

The plugin automatically copies the required Kotlin files.

Verify these files exist in your Android project:

```
android/app/src/main/kotlin/com/techparse/offlinesync/
├── OfflineSyncFunctions.kt
├── ConnectivityMonitor.kt
└── BackgroundSyncWorker.kt
```

Check `AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.ACCESS_WIFI_STATE" />
```

### iOS

Verify these files exist:

```
ios/Sources/OfflineSync/
├── OfflineSyncFunctions.swift
├── ConnectivityMonitor.swift
└── BackgroundSyncScheduler.swift
```

Add to `Info.plist`:

```xml
<key>NSLocalNetworkUsageDescription</key>
<string>This app needs network access to sync your data.</string>

<key>UIBackgroundModes</key>
<array>
    <string>fetch</string>
    <string>processing</string>
</array>
```

---

## ✅ Verify the installation

### Test 1 — Artisan commands

```bash
php artisan list sync
```

Expected:
```
sync:clear   Clear sync queue
sync:pull    Pull remote changes from the server
sync:push    Push local changes to the server
sync:status  Show sync queue status
```

### Test 2 — Create a test item

```bash
php artisan tinker
>>> $task = \App\Models\Task::create(['title' => 'Test Sync']);
>>> \Techparse\OfflineSync\Facades\OfflineSync::getPending()->count();
=> 1
```

### Test 3 — Check status

```bash
php artisan sync:status
```

### Test 4 — Test backend connectivity

```bash
curl https://api.your-app.com/api/sync/ping \
  -H "Authorization: Bearer your-token"
```

Expected response:
```json
{"status":"ok"}
```

---

## 🔍 Troubleshooting

### "Class OfflineSync not found"

```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Migrations fail

```bash
php artisan migrate:rollback
php artisan migrate
```

### API routes not accessible

1. Check Sanctum is installed: `composer show laravel/sanctum`
2. Verify the token is valid
3. Check CORS if the frontend is on a different domain

### Items not syncing

1. Check connectivity: `php artisan sync:status`
2. Check logs: `storage/logs/laravel.log`
3. Verify API URL: `.env` → `SYNC_API_URL`

### "HTTPS required" error

```env
# Local development only
SYNC_REQUIRE_HTTPS=false

# Always use HTTPS in production
SYNC_REQUIRE_HTTPS=true
```

---

## 🎓 Next steps

1. ✅ **Read the Backend guide**: [BACKEND.md](BACKEND.md)
2. ✅ **Configure conflicts**: [CONFLICTS.md](CONFLICTS.md)
3. ✅ **Secure your app**: [SECURITY.md](SECURITY.md)
4. ✅ **Full API reference**: [README.md](../README.md)

---

## 📞 Support

- 📧 Email: offlinessync@techparse.fr
- 🐛 Issues: https://github.com/Kromaric/offlinesync/issues

---

**Happy syncing!** 🚀
