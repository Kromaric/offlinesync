import Foundation
import BackgroundTasks

/// Planificateur de synchronisation en arrière-plan pour iOS
/// Utilise BGTaskScheduler pour exécuter des syncs même quand l'app est fermée
@available(iOS 13.0, *)
public class BackgroundSyncScheduler {
    
    // Identifiants des tâches
    private let refreshTaskIdentifier = "com.vendor.offlinesync.refresh"
    private let processingTaskIdentifier = "com.vendor.offlinesync.processing"
    
    // Configuration
    private var intervalMinutes: Double = 30.0
    private var requireWifi: Bool = false
    
    // MARK: - Initialization
    
    public init() {
        registerBackgroundTasks()
    }
    
    // MARK: - Task Registration
    
    /// Enregistrer les tâches en arrière-plan
    private func registerBackgroundTasks() {
        // BGAppRefreshTask - Sync légère et rapide
        BGTaskScheduler.shared.register(
            forTaskWithIdentifier: refreshTaskIdentifier,
            using: nil
        ) { task in
            self.handleRefreshTask(task: task as! BGAppRefreshTask)
        }
        
        // BGProcessingTask - Sync lourde avec plus de temps
        BGTaskScheduler.shared.register(
            forTaskWithIdentifier: processingTaskIdentifier,
            using: nil
        ) { task in
            self.handleProcessingTask(task: task as! BGProcessingTask)
        }
    }
    
    // MARK: - Scheduling
    
    /// Planifier une synchronisation immédiate
    public func scheduleSyncNow() {
        let request = BGAppRefreshTaskRequest(identifier: refreshTaskIdentifier)
        request.earliestBeginDate = Date()
        
        do {
            try BGTaskScheduler.shared.submit(request)
            print("✅ Immediate sync scheduled")
        } catch {
            print("❌ Could not schedule immediate sync: \(error)")
        }
    }
    
    /// Planifier une synchronisation périodique
    ///
    /// - Parameters:
    ///   - intervalMinutes: Intervalle en minutes (minimum 15 minutes sur iOS)
    ///   - requireWifi: Si true, sync uniquement en WiFi
    public func schedulePeriodicSync(intervalMinutes: Double = 30.0, requireWifi: Bool = false) {
        self.intervalMinutes = max(intervalMinutes, 15.0) // iOS minimum 15 min
        self.requireWifi = requireWifi
        
        // Planifier la prochaine refresh task
        scheduleNextRefresh()
        
        // Planifier une processing task pour sync lourde
        scheduleNextProcessing()
    }
    
    /// Planifier la prochaine refresh task
    private func scheduleNextRefresh() {
        let request = BGAppRefreshTaskRequest(identifier: refreshTaskIdentifier)
        
        // Calculer la prochaine exécution
        let interval = TimeInterval(intervalMinutes * 60)
        request.earliestBeginDate = Date(timeIntervalSinceNow: interval)
        
        do {
            try BGTaskScheduler.shared.submit(request)
            print("✅ Next refresh scheduled in \(intervalMinutes) minutes")
        } catch {
            print("❌ Could not schedule refresh: \(error)")
        }
    }
    
    /// Planifier la prochaine processing task
    private func scheduleNextProcessing() {
        let request = BGProcessingTaskRequest(identifier: processingTaskIdentifier)
        
        // Processing task - peut tourner plus longtemps
        request.requiresNetworkConnectivity = true
        request.requiresExternalPower = false // Ne pas attendre le chargeur
        
        // Calculer la prochaine exécution (2x l'intervalle pour processing)
        let interval = TimeInterval(intervalMinutes * 60 * 2)
        request.earliestBeginDate = Date(timeIntervalSinceNow: interval)
        
        do {
            try BGTaskScheduler.shared.submit(request)
            print("✅ Next processing scheduled in \(intervalMinutes * 2) minutes")
        } catch {
            print("❌ Could not schedule processing: \(error)")
        }
    }
    
    /// Annuler la synchronisation périodique
    public func cancelPeriodicSync() {
        BGTaskScheduler.shared.cancel(taskRequestWithIdentifier: refreshTaskIdentifier)
        BGTaskScheduler.shared.cancel(taskRequestWithIdentifier: processingTaskIdentifier)
        print("✅ Periodic sync cancelled")
    }
    
    /// Annuler toutes les synchronisations
    public func cancelAllSync() {
        BGTaskScheduler.shared.cancelAllTaskRequests()
        print("✅ All syncs cancelled")
    }
    
    // MARK: - Task Handlers
    
    /// Gérer la refresh task (sync rapide)
    private func handleRefreshTask(task: BGAppRefreshTask) {
        // Planifier la prochaine refresh
        scheduleNextRefresh()
        
        // Vérifier la connectivité si nécessaire
        if requireWifi {
            let monitor = ConnectivityMonitor()
            guard monitor.isWifi() else {
                task.setTaskCompleted(success: false)
                return
            }
        }
        
        // Créer une operation pour la sync
        let syncOperation = SyncOperation()
        
        // Gérer l'expiration de la tâche
        task.expirationHandler = {
            syncOperation.cancel()
        }
        
        // Observer la fin de l'opération
        syncOperation.completionBlock = {
            task.setTaskCompleted(success: !syncOperation.isCancelled)
        }
        
        // Lancer la sync
        syncOperation.start()
    }
    
    /// Gérer la processing task (sync complète)
    private func handleProcessingTask(task: BGProcessingTask) {
        // Planifier la prochaine processing
        scheduleNextProcessing()
        
        // Vérifier la connectivité si nécessaire
        if requireWifi {
            let monitor = ConnectivityMonitor()
            guard monitor.isWifi() else {
                task.setTaskCompleted(success: false)
                return
            }
        }
        
        // Créer une operation pour la sync complète
        let syncOperation = SyncOperation(fullSync: true)
        
        // Gérer l'expiration de la tâche
        task.expirationHandler = {
            syncOperation.cancel()
        }
        
        // Observer la fin de l'opération
        syncOperation.completionBlock = {
            task.setTaskCompleted(success: !syncOperation.isCancelled)
        }
        
        // Lancer la sync
        syncOperation.start()
    }
}

// MARK: - Sync Operation

/// Opération de synchronisation
private class SyncOperation: Operation {
    
    private let fullSync: Bool
    
    init(fullSync: Bool = false) {
        self.fullSync = fullSync
        super.init()
    }
    
    override func main() {
        if isCancelled { return }
        
        // Appeler la fonction de sync
        let syncFunctions = OfflineSyncFunctions()
        let params: [String: Any] = fullSync ? [:] : ["resources": []]
        
        let result = syncFunctions.runSync(params)
        
        if let success = result["success"] as? Bool, success {
            print("✅ Background sync completed: \(result)")
        } else {
            print("❌ Background sync failed: \(result)")
        }
    }
}

// MARK: - iOS 12 Fallback

/// Version simplifiée pour iOS 12 et inférieur (sans BGTaskScheduler)
@available(iOS, deprecated: 13.0, message: "Use BackgroundSyncScheduler for iOS 13+")
public class LegacyBackgroundSyncScheduler {
    
    private var timer: Timer?
    private var intervalMinutes: Double = 30.0
    
    public init() {}
    
    public func schedulePeriodicSync(intervalMinutes: Double = 30.0, requireWifi: Bool = false) {
        self.intervalMinutes = intervalMinutes
        
        // Utiliser un timer simple (fonctionne uniquement quand l'app est active)
        timer?.invalidate()
        timer = Timer.scheduledTimer(
            withTimeInterval: intervalMinutes * 60,
            repeats: true
        ) { [weak self] _ in
            self?.performSync()
        }
    }
    
    public func cancelPeriodicSync() {
        timer?.invalidate()
        timer = nil
    }
    
    private func performSync() {
        let syncFunctions = OfflineSyncFunctions()
        _ = syncFunctions.runSync([:])
    }
}
