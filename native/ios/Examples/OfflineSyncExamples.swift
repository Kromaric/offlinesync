import Foundation
import OfflineSync

/// Exemples d'utilisation du plugin OfflineSync pour iOS
class OfflineSyncExamples {
    
    private let syncFunctions = OfflineSyncFunctions()
    private let connectivityMonitor = ConnectivityMonitor()
    private let backgroundSync: BackgroundSyncScheduler
    
    init() {
        if #available(iOS 13.0, *) {
            backgroundSync = BackgroundSyncScheduler()
        } else {
            // Fallback pour iOS 12
            fatalError("iOS 13+ required for BackgroundSyncScheduler")
        }
    }
    
    // MARK: - Example 1: Queue Action
    
    func queueExample() {
        let params: [String: Any] = [
            "resource": "tasks",
            "resource_id": "123",
            "operation": "update",
            "data": [
                "title": "Ma tâche",
                "completed": false
            ],
            "timestamp": "2025-02-06T10:30:00Z"
        ]
        
        let result = syncFunctions.queueAction(params)
        
        if let success = result["success"] as? Bool, success {
            let queueId = result["queue_id"] as? Int ?? 0
            print("✅ Item queued with ID: \(queueId)")
        } else {
            let error = result["error"] as? String ?? "Unknown error"
            print("❌ Error: \(error)")
        }
    }
    
    // MARK: - Example 2: Manual Sync
    
    func syncExample() {
        let params: [String: Any] = [
            "resources": ["tasks", "users"]
        ]
        
        let result = syncFunctions.runSync(params)
        
        if let success = result["success"] as? Bool, success {
            print("✅ Synced: \(result["synced"] ?? 0)")
            print("   Failed: \(result["failed"] ?? 0)")
            print("   Conflicts: \(result["conflicts"] ?? 0)")
        } else {
            print("❌ Sync failed: \(result["error"] ?? "Unknown")")
        }
    }
    
    // MARK: - Example 3: Get Status
    
    func statusExample() {
        let result = syncFunctions.getStatus([:])
        
        print("📊 Status:")
        print("   Pending items: \(result["pending_count"] ?? 0)")
        print("   Last sync: \(result["last_sync"] ?? "Never")")
        print("   Is syncing: \(result["is_syncing"] ?? false)")
        print("   Is online: \(result["is_online"] ?? false)")
        print("   Connection: \(result["connection_type"] ?? "unknown")")
    }
    
    // MARK: - Example 4: Start Monitoring
    
    func startMonitoringExample() {
        let result = syncFunctions.startMonitoring([:])
        
        if let success = result["success"] as? Bool, success {
            print("✅ Monitoring started")
        }
    }
    
    // MARK: - Example 5: Check Connectivity
    
    func checkConnectivityExample() {
        print("🌐 Connectivity:")
        print("   Online: \(connectivityMonitor.isOnline())")
        print("   WiFi: \(connectivityMonitor.isWifi())")
        print("   Cellular: \(connectivityMonitor.isCellular())")
        print("   Type: \(connectivityMonitor.getConnectionType())")
        print("   Expensive: \(connectivityMonitor.isExpensiveConnection())")
        print("   Constrained: \(connectivityMonitor.isConstrainedConnection())")
        
        let info = connectivityMonitor.getConnectionInfo()
        print("   Detailed info: \(info)")
    }
    
    // MARK: - Example 6: Schedule Periodic Sync
    
    func schedulePeriodicSyncExample() {
        let params: [String: Any] = [
            "interval_minutes": 30,
            "require_wifi": true
        ]
        
        let result = syncFunctions.schedulePeriodicSync(params)
        
        if let success = result["success"] as? Bool, success {
            print("✅ Periodic sync scheduled")
            print("   Interval: \(result["interval_minutes"] ?? 30) minutes")
            print("   Require WiFi: \(result["require_wifi"] ?? false)")
        }
    }
    
    // MARK: - Example 7: Immediate Background Sync
    
    @available(iOS 13.0, *)
    func immediateSyncExample() {
        backgroundSync.scheduleSyncNow()
        print("✅ Immediate sync scheduled")
    }
    
    // MARK: - Example 8: Monitoring with Callback
    
    func monitoringWithCallbackExample() {
        connectivityMonitor.startMonitoring { isOnline in
            if isOnline {
                print("✅ Connection restored - triggering sync")
                if #available(iOS 13.0, *) {
                    self.backgroundSync.scheduleSyncNow()
                }
            } else {
                print("❌ Connection lost")
            }
        }
        
        print("✅ Monitoring started with callback")
    }
    
    // MARK: - Example 9: Complete Setup
    
    @available(iOS 13.0, *)
    func completeSetupExample() {
        print("🚀 Setting up OfflineSync...")
        
        // 1. Start monitoring
        connectivityMonitor.startMonitoring { [weak self] isOnline in
            guard let self = self else { return }
            
            if isOnline {
                print("✅ Online - syncing...")
                self.backgroundSync.scheduleSyncNow()
            } else {
                print("📴 Offline - will sync when connection is restored")
            }
        }
        
        // 2. Schedule periodic sync (every 30 min, WiFi only)
        backgroundSync.schedulePeriodicSync(
            intervalMinutes: 30,
            requireWifi: true
        )
        
        // 3. Initial sync if online
        if connectivityMonitor.isOnline() {
            backgroundSync.scheduleSyncNow()
        }
        
        print("✅ OfflineSync setup complete!")
    }
    
    // MARK: - Example 10: Cleanup
    
    @available(iOS 13.0, *)
    func cleanupExample() {
        // Stop monitoring
        connectivityMonitor.stopMonitoring()
        
        // Cancel periodic sync
        backgroundSync.cancelPeriodicSync()
        
        print("✅ Cleanup done")
    }
    
    // MARK: - Example 11: AppDelegate Integration
    
    @available(iOS 13.0, *)
    func appDelegateExample() {
        // Dans AppDelegate.swift
        
        /*
        import UIKit
        import BackgroundTasks
        
        @main
        class AppDelegate: UIResponder, UIApplicationDelegate {
            
            func application(
                _ application: UIApplication,
                didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
            ) -> Bool {
                
                // Enregistrer les background tasks
                registerBackgroundTasks()
                
                // Setup OfflineSync
                setupOfflineSync()
                
                return true
            }
            
            private func registerBackgroundTasks() {
                // Déjà géré par BackgroundSyncScheduler
            }
            
            private func setupOfflineSync() {
                let examples = OfflineSyncExamples()
                examples.completeSetupExample()
            }
            
            func applicationDidEnterBackground(_ application: UIApplication) {
                // Les background tasks sont automatiquement gérés
            }
        }
        */
        
        print("📝 See code comments for AppDelegate integration")
    }
    
    // MARK: - Example 12: SwiftUI Integration
    
    func swiftUIExample() {
        /*
        import SwiftUI
        
        @main
        struct MyApp: App {
            @StateObject private var syncManager = SyncManager()
            
            var body: some Scene {
                WindowGroup {
                    ContentView()
                        .environmentObject(syncManager)
                }
            }
        }
        
        class SyncManager: ObservableObject {
            @Published var isOnline = false
            @Published var pendingCount = 0
            
            private let syncFunctions = OfflineSyncFunctions()
            private let connectivityMonitor = ConnectivityMonitor()
            
            init() {
                setupMonitoring()
            }
            
            private func setupMonitoring() {
                connectivityMonitor.startMonitoring { [weak self] isOnline in
                    DispatchQueue.main.async {
                        self?.isOnline = isOnline
                    }
                }
                
                updateStatus()
            }
            
            func updateStatus() {
                let status = syncFunctions.getStatus([:])
                pendingCount = status["pending_count"] as? Int ?? 0
            }
            
            func sync() {
                _ = syncFunctions.runSync([:])
                updateStatus()
            }
        }
        */
        
        print("📝 See code comments for SwiftUI integration")
    }
}
