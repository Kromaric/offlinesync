# Changelog - Android Native Code

## v1.0.0

### ✨ Features

#### OfflineSyncFunctions.kt
- ✅ `queueAction()` — Queue operations for sync
- ✅ `runSync()` — Manual sync trigger
- ✅ `getStatus()` — Queue and connectivity status
- ✅ `startMonitoring()` — Start connectivity monitoring
- ✅ `stopMonitoring()` — Stop connectivity monitoring
- ✅ `schedulePeriodicSync()` — Schedule periodic background sync
- ✅ `cancelPeriodicSync()` — Cancel periodic sync

#### ConnectivityMonitor.kt
- ✅ WiFi / Mobile data detection
- ✅ Real-time connection change monitoring
- ✅ Detailed connection info (bandwidth, type)
- ✅ Metered connection detection (limited data)
- ✅ Android 7.0+ support (API 24+)

#### BackgroundSyncWorker.kt
- ✅ Background sync with WorkManager
- ✅ Configurable periodic sync
- ✅ Network constraints (WiFi only, connected, etc.)
- ✅ Battery constraint (avoid draining)
- ✅ Automatic retry with exponential backoff
- ✅ Persistence across reboots
- ✅ Respects Doze mode and App Standby

### 📦 Dependencies
- AndroidX Core KTX 1.12.0
- WorkManager 2.9.0
- OkHttp 4.12.0
- Kotlin 1.9.0
- Coroutines 1.7.3

### 📱 Platform Support
- Min SDK: 24 (Android 7.0)
- Target SDK: 34 (Android 14)
- Compile SDK: 34

### 🔐 Permissions
- INTERNET
- ACCESS_NETWORK_STATE
- ACCESS_WIFI_STATE
- CHANGE_NETWORK_STATE

### ⚠️ Known Issues
- `callPhpFunction()` requires the actual NativePHP bridge implementation
- Unit tests to be added

### 🔜 Planned for v1.1.0
- Unit tests with JUnit
- Instrumented tests with Espresso
- Sync notifications
- Detailed sync statistics
- Verbose debug logging mode
