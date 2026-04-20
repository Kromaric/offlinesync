# OfflineSync - Code Natif iOS

Documentation pour le code natif iOS (Swift) du plugin OfflineSync.

---

## 📁 Structure

```
ios/
├── Package.swift                   # Configuration Swift Package Manager
├── Info.plist                      # Permissions et configuration
├── Sources/
│   └── OfflineSync/
│       ├── OfflineSyncFunctions.swift      # Bridge functions principales
│       ├── ConnectivityMonitor.swift       # Monitoring réseau
│       └── BackgroundSyncScheduler.swift   # Sync en arrière-plan
└── Examples/
    └── OfflineSyncExamples.swift   # Exemples d'utilisation
```

---

## 🔧 Composants

### OfflineSyncFunctions.swift

Bridge principal entre PHP/Laravel et iOS. Expose les fonctions :

**Fonctions disponibles :**
- ✅ `queueAction(_:)` - Ajouter une action à la queue
- ✅ `runSync(_:)` - Lancer une synchronisation manuelle
- ✅ `getStatus(_:)` - Obtenir le statut de synchronisation
- ✅ `startMonitoring(_:)` - Démarrer le monitoring de connectivité
- ✅ `stopMonitoring(_:)` - Arrêter le monitoring
- ✅ `schedulePeriodicSync(_:)` - Planifier une sync périodique
- ✅ `cancelPeriodicSync(_:)` - Annuler la sync périodique

**Exemple d'utilisation :**

```swift
let syncFunctions = OfflineSyncFunctions()

// Queue une action
let params: [String: Any] = [
    "resource": "tasks",
    "resource_id": "123",
    "operation": "update",
    "data": ["title": "Ma tâche"],
    "timestamp": ISO8601DateFormatter().string(from: Date())
]

let result = syncFunctions.queueAction(params)
if let success = result["success"] as? Bool, success {
    print("Item queued successfully")
}
```

---

### ConnectivityMonitor.swift

Moniteur de connectivité réseau utilisant le framework `Network`.

**Fonctionnalités :**
- ✅ Détection WiFi / Cellular / Ethernet
- ✅ Callback temps réel sur changement de connexion
- ✅ Informations détaillées sur la connexion
- ✅ Gestion du Low Power Mode

**Méthodes :**

```swift
public class ConnectivityMonitor {
    // Vérifier si online
    func isOnline() -> Bool
    
    // Vérifier si WiFi
    func isWifi() -> Bool
    
    // Vérifier si Cellular
    func isCellular() -> Bool
    
    // Obtenir le type de connexion
    func getConnectionType() -> String  // "wifi", "cellular", "offline"
    
    // Démarrer le monitoring
    func startMonitoring(callback: @escaping (Bool) -> Void)
    
    // Arrêter le monitoring
    func stopMonitoring()
    
    // Infos détaillées
    func getConnectionInfo() -> [String: Any]
    
    // Vérifier si connexion mesurée (limited data)
    func isExpensive() -> Bool
}
```

**Exemple :**

```swift
let monitor = ConnectivityMonitor()

// Vérification simple
if monitor.isOnline() {
    print("Connected!")
}

// Monitoring en temps réel
monitor.startMonitoring { isOnline in
    if isOnline {
        print("✅ Connection restored")
        // Déclencher une sync
    } else {
        print("❌ Connection lost")
    }
}

// Infos détaillées
let info = monitor.getConnectionInfo()
print("Type: \(info["connection_type"] ?? "unknown")")
print("WiFi: \(info["is_wifi"] ?? false)")
```

---

### BackgroundSyncScheduler.swift

Planificateur de synchronisation en arrière-plan utilisant `BackgroundTasks`.

**Fonctionnalités :**
- ✅ Sync périodique en arrière-plan (même app fermée)
- ✅ Respect du Doze mode et Low Power Mode
- ✅ Contraintes configurables (WiFi, batterie)
- ✅ Sync immédiate ponctuelle

**Méthodes :**

```swift
public class BackgroundSyncScheduler {
    // Enregistrer la tâche de fond
    func registerBackgroundTask()
    
    // Planifier une sync immédiate
    func scheduleSyncNow()
    
    // Planifier une sync périodique
    func schedulePeriodicSync(intervalMinutes: Double = 30)
    
    // Annuler la sync périodique
    func cancelPeriodicSync()
    
    // Obtenir le statut des tâches
    func getTasksStatus() -> [String: String]
}
```

**Exemple :**

```swift
let scheduler = BackgroundSyncScheduler()

// Setup initial (dans AppDelegate)
scheduler.registerBackgroundTask()

// Planifier sync périodique (toutes les 30 min)
scheduler.schedulePeriodicSync(intervalMinutes: 30)

// Sync immédiate
scheduler.scheduleSyncNow()
```

---

## 📦 Configuration

### 1. Swift Package Manager

**Package.swift** (déjà inclus) :

```swift
// swift-tools-version:5.9
import PackageDescription

let package = Package(
    name: "OfflineSync",
    platforms: [
        .iOS(.v14)
    ],
    products: [
        .library(
            name: "OfflineSync",
            targets: ["OfflineSync"]
        ),
    ],
    dependencies: [],
    targets: [
        .target(
            name: "OfflineSync",
            dependencies: [],
            path: "Sources/OfflineSync"
        ),
    ]
)
```

### 2. Info.plist

**Permissions requises :**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <!-- Description pour accès réseau -->
    <key>NSLocalNetworkUsageDescription</key>
    <string>Cette app a besoin d'accéder au réseau pour synchroniser vos données avec le serveur.</string>
    
    <!-- App Transport Security -->
    <key>NSAppTransportSecurity</key>
    <dict>
        <key>NSAllowsArbitraryLoads</key>
        <false/>
        <!-- En développement local seulement -->
        <key>NSExceptionDomains</key>
        <dict>
            <key>localhost</key>
            <dict>
                <key>NSExceptionAllowsInsecureHTTPLoads</key>
                <true/>
            </dict>
        </dict>
    </dict>
    
    <!-- Background Modes -->
    <key>UIBackgroundModes</key>
    <array>
        <string>fetch</string>
        <string>processing</string>
    </array>
</dict>
</plist>
```

### 3. Capabilities

Dans Xcode, activer :
- ✅ **Background Modes** → Background fetch
- ✅ **Background Modes** → Background processing
- ✅ **Network Extensions** (si nécessaire)

---

## 🚀 Utilisation

### Setup initial dans AppDelegate

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
        
        // 1. Enregistrer la tâche de fond
        backgroundScheduler.registerBackgroundTask()
        
        // 2. Démarrer le monitoring de connectivité
        connectivityMonitor.startMonitoring { [weak self] isOnline in
            if isOnline {
                print("✅ Connection restored - syncing...")
                self?.backgroundScheduler.scheduleSyncNow()
            } else {
                print("❌ Connection lost")
            }
        }
        
        // 3. Planifier sync périodique (30 min)
        backgroundScheduler.schedulePeriodicSync(intervalMinutes: 30)
        
        // 4. Sync initiale si online
        if connectivityMonitor.isOnline() {
            backgroundScheduler.scheduleSyncNow()
        }
        
        return true
    }
    
    func applicationWillTerminate(_ application: UIApplication) {
        // Cleanup
        connectivityMonitor.stopMonitoring()
    }
}
```

### Dans un ViewController

```swift
import UIKit
import OfflineSync

class TasksViewController: UIViewController {
    
    let syncFunctions = OfflineSyncFunctions()
    
    override func viewDidLoad() {
        super.viewDidLoad()
        
        // Afficher le statut
        displaySyncStatus()
    }
    
    @IBAction func syncButtonTapped(_ sender: UIButton) {
        // Sync manuelle
        let result = syncFunctions.runSync([:])
        
        if let success = result["success"] as? Bool, success {
            let synced = result["synced"] as? Int ?? 0
            showAlert(message: "\(synced) items synchronisés")
        } else {
            let error = result["error"] as? String ?? "Erreur inconnue"
            showAlert(message: "Erreur: \(error)")
        }
    }
    
    func displaySyncStatus() {
        let status = syncFunctions.getStatus([:])
        
        let pendingCount = status["pending_count"] as? Int ?? 0
        let isOnline = status["is_online"] as? Bool ?? false
        let connectionType = status["connection_type"] as? String ?? "unknown"
        
        statusLabel.text = """
        Pending: \(pendingCount)
        Status: \(isOnline ? "Online" : "Offline")
        Type: \(connectionType)
        """
    }
}
```

---

## 🎯 Exemples complets

Voir `Examples/OfflineSyncExamples.swift` pour 10 exemples détaillés :

1. ✅ Queue d'action
2. ✅ Sync manuelle
3. ✅ Obtenir le statut
4. ✅ Monitoring de connectivité
5. ✅ Vérification de connexion
6. ✅ Sync périodique
7. ✅ Sync immédiate
8. ✅ Monitoring avec callback
9. ✅ Statut des tâches de fond
10. ✅ Setup complet

---

## 🔒 Sécurité iOS

### Network.framework

Le code utilise le framework `Network` moderne d'Apple :

```swift
import Network

let monitor = NWPathMonitor()
monitor.pathUpdateHandler = { path in
    if path.status == .satisfied {
        // Connected
    }
}
```

**Avantages :**
- ✅ API moderne et performante
- ✅ Support natif du VPN
- ✅ Détection des connexions mesurées
- ✅ Callbacks temps réel

### Background Tasks

```swift
import BackgroundTasks

BGTaskScheduler.shared.register(
    forTaskWithIdentifier: "com.vendor.offlinesync.sync",
    using: nil
) { task in
    self.handleBackgroundSync(task: task as! BGAppRefreshTask)
}
```

**Limitations iOS :**
- ⚠️ Pas de garantie d'exécution exacte
- ⚠️ Système peut retarder si batterie faible
- ⚠️ Maximum ~30 secondes par tâche

---

## 📱 Versions iOS supportées

| Version iOS | Support |
|-------------|---------|
| iOS 14.0+ | ✅ Complet |
| iOS 13.0 | ⚠️ Partiel (pas de BackgroundTasks) |
| iOS 12.0- | ❌ Non supporté |

---

## 🐛 Debugging

### Logs Xcode

```swift
import os.log

let logger = OSLog(subsystem: "com.vendor.offlinesync", category: "sync")

os_log("Sync started", log: logger, type: .info)
os_log("Error: %@", log: logger, type: .error, error.localizedDescription)
```

### Simuler une perte de connexion

1. Simulator → Features → Trigger iCloud Sync
2. Ou utiliser le Network Link Conditioner

### Tester les Background Tasks

```bash
# Simuler une tâche de fond
e -l objc -- (void)[[BGTaskScheduler sharedScheduler] _simulateLaunchForTaskWithIdentifier:@"com.vendor.offlinesync.sync"]
```

---

## 📊 Performance

### Optimisations

1. **Batch les requêtes** - Ne pas sync item par item
2. **WiFi uniquement** - Économiser les données mobiles
3. **Background fetch** - Sync quand le système le permet
4. **Compression** - Compresser les payloads si gros

### Métriques

```swift
let start = Date()
// ... sync
let duration = Date().timeIntervalSince(start)
print("Sync duration: \(duration)s")
```

---

## ⚠️ Point d'attention

La méthode `callPhpFunction()` dans `OfflineSyncFunctions.swift` est actuellement un stub qui lance une erreur `NotImplementedError`.

Elle devra être implémentée selon l'API réelle du bridge NativePHP une fois disponible.

**Stub actuel :**

```swift
private func callPhpFunction(function: String, params: [String: Any]) throws -> [String: Any] {
    // TODO: Implémenter avec le vrai bridge NativePHP
    throw NSError(
        domain: "OfflineSync",
        code: -1,
        userInfo: [NSLocalizedDescriptionKey: "NativePHP Bridge not implemented yet"]
    )
}
```

---

## 🔜 Roadmap iOS

- [ ] Implémenter le bridge NativePHP réel
- [ ] Support watchOS
- [ ] Support Widgets
- [ ] Support Shortcuts
- [ ] CloudKit sync optionnel

---

## 📚 Ressources

- [Network Framework](https://developer.apple.com/documentation/network)
- [Background Tasks](https://developer.apple.com/documentation/backgroundtasks)
- [App Transport Security](https://developer.apple.com/documentation/bundleresources/information_property_list/nsapptransportsecurity)

---

## 📞 Support

- 📧 Email : support@vendorname.com
- 📖 Documentation : https://docs.vendorname.com/offline-sync

---

**Code iOS prêt !** 🍎
