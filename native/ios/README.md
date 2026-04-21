# OfflineSync - iOS Native Code

Native iOS (Swift) code for the OfflineSync plugin.

---

## 📁 Structure

```
ios/
├── Package.swift                   # Swift Package Manager configuration
├── Info.plist                      # Permissions and configuration
├── Sources/
│   └── OfflineSync/
│       ├── OfflineSyncFunctions.swift      # Main bridge functions
│       ├── ConnectivityMonitor.swift       # Network monitoring
│       └── BackgroundSyncScheduler.swift   # Background sync scheduler
└── Examples/
    └── OfflineSyncExamples.swift   # Usage examples
```

---

## 🔧 Components

### OfflineSyncFunctions.swift

Main bridge between PHP/Laravel and iOS. Exposes:

- ✅ `queueAction(_:)` — Queue an operation for sync
- ✅ `runSync(_:)` — Trigger a manual sync
- ✅ `getStatus(_:)` — Get sync status
- ✅ `startMonitoring(_:)` — Start connectivity monitoring
- ✅ `stopMonitoring(_:)` — Stop monitoring
- ✅ `schedulePeriodicSync(_:)` — Schedule periodic sync
- ✅ `cancelPeriodicSync(_:)` — Cancel periodic sync

**Example:**

```swift
let syncFunctions = OfflineSyncFunctions()

let params: [String: Any] = [
    "resource": "tasks",
    "resource_id": "123",
    "operation": "update",
    "data": ["title": "My task"],
    "timestamp": ISO8601DateFormatter().string(from: Date()),
]

let result = syncFunctions.queueAction(params)
if let success = result["success"] as? Bool, success {
    print("Item queued successfully")
}
```

---

### ConnectivityMonitor.swift

Network connectivity monitor using the `Network` framework.

**Features:**
- ✅ WiFi / Cellular / Ethernet detection
- ✅ Real-time connection change callbacks
- ✅ Detailed connection info
- ✅ Low Power Mode handling

**API:**

```swift
public class ConnectivityMonitor {
    func isOnline() -> Bool
    func isWifi() -> Bool
    func isCellular() -> Bool
    func getConnectionType() -> String  // "wifi", "cellular", "offline"
    func startMonitoring(callback: @escaping (Bool) -> Void)
    func stopMonitoring()
    func getConnectionInfo() -> [String: Any]
    func isExpensive() -> Bool          // metered / limited data
}
```

**Example:**

```swift
let monitor = ConnectivityMonitor()

monitor.startMonitoring { isOnline in
    if isOnline {
        print("✅ Connection restored")
    } else {
        print("❌ Connection lost")
    }
}
```

---

### BackgroundSyncScheduler.swift

Background sync scheduler using `BackgroundTasks`.

**Features:**
- ✅ Periodic sync in background (even when app is closed)
- ✅ Respects Doze mode and Low Power Mode
- ✅ Configurable constraints (WiFi, battery)
- ✅ One-shot immediate sync

**API:**

```swift
public class BackgroundSyncScheduler {
    func registerBackgroundTask()
    func scheduleSyncNow()
    func schedulePeriodicSync(intervalMinutes: Double = 30)
    func cancelPeriodicSync()
    func getTasksStatus() -> [String: String]
}
```

**Example:**

```swift
let scheduler = BackgroundSyncScheduler()

// Register at app start (AppDelegate)
scheduler.registerBackgroundTask()

// Schedule periodic sync every 30 min
scheduler.schedulePeriodicSync(intervalMinutes: 30)

// Immediate sync
scheduler.scheduleSyncNow()
```

---

## 📦 Configuration

### Swift Package Manager

`Package.swift` is already included:

```swift
// swift-tools-version:5.9
import PackageDescription

let package = Package(
    name: "OfflineSync",
    platforms: [.iOS(.v14)],
    products: [
        .library(name: "OfflineSync", targets: ["OfflineSync"]),
    ],
    targets: [
        .target(name: "OfflineSync", dependencies: [], path: "Sources/OfflineSync"),
    ]
)
```

### Info.plist — Required permissions

```xml
<key>NSLocalNetworkUsageDescription</key>
<string>This app needs network access to sync your data with the server.</string>

<key>UIBackgroundModes</key>
<array>
    <string>fetch</string>
    <string>processing</string>
</array>

<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsArbitraryLoads</key>
    <false/>
    <!-- Local development only -->
    <key>NSExceptionDomains</key>
    <dict>
        <key>localhost</key>
        <dict>
            <key>NSExceptionAllowsInsecureHTTPLoads</key>
            <true/>
        </dict>
    </dict>
</dict>
```

### Xcode Capabilities

Enable in Xcode:
- ✅ **Background Modes** → Background fetch
- ✅ **Background Modes** → Background processing

---

## 🚀 Integration

### AppDelegate setup

```swift
import UIKit
import OfflineSync

@main
class AppDelegate: UIResponder, UIApplicationDelegate {

    let syncFunctions = OfflineSyncFunctions()
    let connectivityMonitor = ConnectivityMonitor()
    let backgroundScheduler = BackgroundSyncScheduler()

    func application(_ application: UIApplication,
                     didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {

        // 1. Register background task
        backgroundScheduler.registerBackgroundTask()

        // 2. Start connectivity monitoring
        connectivityMonitor.startMonitoring { [weak self] isOnline in
            if isOnline {
                self?.backgroundScheduler.scheduleSyncNow()
            }
        }

        // 3. Schedule periodic sync (every 30 min)
        backgroundScheduler.schedulePeriodicSync(intervalMinutes: 30)

        // 4. Initial sync if online
        if connectivityMonitor.isOnline() {
            backgroundScheduler.scheduleSyncNow()
        }

        return true
    }

    func applicationWillTerminate(_ application: UIApplication) {
        connectivityMonitor.stopMonitoring()
    }
}
```

---

## 📱 iOS Version Support

| iOS Version | Support |
|-------------|---------|
| iOS 14.0+   | ✅ Full |
| iOS 13.0    | ⚠️ Partial (no BackgroundTasks) |
| iOS 12.0-   | ❌ Not supported |

---

## 🐛 Debugging

### Xcode console logs

```swift
import os.log

let logger = OSLog(subsystem: "com.techparse.offlinesync", category: "sync")
os_log("Sync started", log: logger, type: .info)
```

### Simulate background task

```bash
e -l objc -- (void)[[BGTaskScheduler sharedScheduler] _simulateLaunchForTaskWithIdentifier:@"com.techparse.offlinesync.sync"]
```

---

## ⚠️ Known Limitation

`callPhpFunction()` in `OfflineSyncFunctions.swift` is currently a stub — it throws a `NotImplementedError` until the actual NativePHP bridge API is available:

```swift
private func callPhpFunction(function: String, params: [String: Any]) throws -> [String: Any] {
    // TODO: implement with the real NativePHP bridge
    throw NSError(domain: "OfflineSync", code: -1,
                  userInfo: [NSLocalizedDescriptionKey: "NativePHP Bridge not implemented yet"])
}
```

---

## 🔜 Roadmap

- [ ] Implement real NativePHP bridge
- [ ] watchOS support
- [ ] Widget support
- [ ] Shortcuts support
- [ ] Optional CloudKit sync

---

## 📚 Resources

- [Network Framework](https://developer.apple.com/documentation/network)
- [Background Tasks](https://developer.apple.com/documentation/backgroundtasks)
- [App Transport Security](https://developer.apple.com/documentation/bundleresources/information_property_list/nsapptransportsecurity)

---

## 📞 Support

- 📧 Email: support@techparse.fr
- 🐛 Issues: https://github.com/Kromaric/offlinesync/issues
