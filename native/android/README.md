# OfflineSync - Android Native Code

This directory contains the Android (Kotlin) native code for the OfflineSync plugin.

## 📁 Structure

```
android/
├── build.gradle                    # Gradle configuration
├── src/
│   └── main/
│       ├── AndroidManifest.xml     # Permissions
│       └── kotlin/
│           └── com/techparse/offlinesync/
│               ├── OfflineSyncFunctions.kt      # Main bridge functions
│               ├── ConnectivityMonitor.kt       # Connectivity monitoring
│               └── BackgroundSyncWorker.kt      # Background sync worker
```

## 🔧 Components

### OfflineSyncFunctions.kt

Bridge between PHP/Laravel and Android. Exposes:
- `queueAction()` — Queue an operation for sync
- `runSync()` — Trigger a manual sync
- `getStatus()` — Get current sync status
- `startMonitoring()` — Start connectivity monitoring
- `stopMonitoring()` — Stop connectivity monitoring
- `schedulePeriodicSync()` — Schedule periodic background sync
- `cancelPeriodicSync()` — Cancel periodic sync

### ConnectivityMonitor.kt

Network connectivity detection:
- Detects WiFi / Mobile data
- Listens for connection changes in real time
- Provides detailed connection info (bandwidth, type)
- Detects metered connections (limited data)

### BackgroundSyncWorker.kt

Background synchronization using WorkManager:
- Periodic sync even when the app is closed
- Configurable constraints (WiFi only, battery, etc.)
- Automatic retry on failure
- Exponential backoff policy
- Persists across device reboots

## 📦 Dependencies

```gradle
// WorkManager for background sync
implementation 'androidx.work:work-runtime-ktx:2.9.0'

// OkHttp for HTTP requests
implementation 'com.squareup.okhttp3:okhttp:4.12.0'

// Coroutines
implementation 'org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3'
```

## 🔐 Required Permissions

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.ACCESS_WIFI_STATE" />
<uses-permission android:name="android.permission.CHANGE_NETWORK_STATE" />
```

## 🚀 Usage

### From PHP/NativePHP

Functions are called automatically via the NativePHP bridge:

```php
// PHP side
OfflineSync::queue($model, 'create');
```

The NativePHP bridge automatically calls:

```kotlin
// Android side
OfflineSyncFunctions.queueAction(params)
```

### Schedule periodic sync

```php
// Sync every 30 minutes, WiFi only
NativeBridge::call('OfflineSync.schedulePeriodicSync', [
    'interval_minutes' => 30,
    'require_wifi' => true,
]);
```

## 📱 Supported Versions

- **Min SDK**: 24 (Android 7.0 Nougat)
- **Target SDK**: 34 (Android 14)
- **Compile SDK**: 34

## ⚡ WorkManager

The plugin uses WorkManager to guarantee:
- Execution even if the app is killed
- Respect for constraints (WiFi, battery, etc.)
- Automatic retry with backoff
- Task persistence after reboot

## 🔄 Sync Flow

```
1. Data change → Local queue (SQLite)
2. ConnectivityMonitor detects connection
3. BackgroundSyncWorker triggered
4. OfflineSyncFunctions.runSync() called
5. Bridge → PHP code → Laravel API
6. Conflict resolution
7. Local update
```

## 🧪 Testing

Test connectivity:

```kotlin
val monitor = ConnectivityMonitor(context)
println("Online: ${monitor.isOnline()}")
println("Type: ${monitor.getConnectionType()}")
println("Info: ${monitor.getConnectionInfo()}")
```

Test background sync:

```kotlin
val worker = BackgroundSyncWorker.getInstance(context)
worker.scheduleSyncNow()
```

## 🐛 Debugging

Enable WorkManager logs:

```kotlin
WorkManager.getInstance(context).apply {
    val config = Configuration.Builder()
        .setMinimumLoggingLevel(android.util.Log.DEBUG)
        .build()
}
```

## ⚠️ Important

`OfflineSyncFunctions.kt` contains a `callPhpFunction()` method that must be implemented against the actual NativePHP bridge API. It currently throws a `NotImplementedError`.

## 📝 Notes

- Workers are persisted across device reboots
- Periodic sync adapts to system constraints (Doze mode, App Standby)
- On metered WiFi the system may delay sync
- Battery constraints are respected automatically
