import Foundation
import Network

/// Bridge Functions pour OfflineSync iOS
/// Interface entre le code PHP/Laravel et le code natif Swift
@objc public class OfflineSyncFunctions: NSObject {
    
    private let connectivityMonitor = ConnectivityMonitor()
    private let backgroundSync = BackgroundSyncScheduler()
    
    // MARK: - Queue Management
    
    /// Queue une action pour synchronisation
    ///
    /// - Parameter params: Dictionary contenant:
    ///   - resource: String - nom de la ressource (ex: "tasks")
    ///   - resource_id: String? - ID de la ressource (nil pour create)
    ///   - operation: String - "create", "update" ou "delete"
    ///   - data: Dictionary - données à synchroniser
    ///   - timestamp: String - timestamp ISO8601
    /// - Returns: Dictionary {success: Bool, queue_id: Int}
    @objc public func queueAction(_ params: [String: Any]) -> [String: Any] {
        guard let resource = params["resource"] as? String,
              let operation = params["operation"] as? String,
              let data = params["data"] as? [String: Any],
              let timestamp = params["timestamp"] as? String else {
            return createErrorResponse("Invalid parameters")
        }
        
        // Valider l'opération
        guard isValidOperation(operation) else {
            return createErrorResponse("Invalid operation: \(operation)")
        }
        
        do {
            // Appeler le PHP via le bridge NativePHP
            let result = try callPhpFunction(
                function: "OfflineSync::queue",
                params: [
                    "resource": resource,
                    "resource_id": params["resource_id"] as Any,
                    "operation": operation,
                    "data": data,
                    "timestamp": timestamp
                ]
            )
            
            return [
                "success": true,
                "queue_id": result["id"] ?? 0
            ]
        } catch {
            return createErrorResponse(error.localizedDescription)
        }
    }
    
    // MARK: - Synchronization
    
    /// Lancer une synchronisation manuelle
    ///
    /// - Parameter params: Dictionary contenant:
    ///   - resources: [String]? - liste des ressources (nil = toutes)
    /// - Returns: Dictionary {success: Bool, synced: Int, failed: Int, conflicts: Int}
    @objc public func runSync(_ params: [String: Any]) -> [String: Any] {
        // Vérifier la connexion
        guard connectivityMonitor.isOnline() else {
            return createErrorResponse("No internet connection")
        }
        
        do {
            let resources = params["resources"] as? [String]
            let result = try callPhpFunction(
                function: "OfflineSync::sync",
                params: ["resources": resources as Any]
            )
            
            return [
                "success": true,
                "synced": result["synced"] ?? 0,
                "failed": result["failed"] ?? 0,
                "conflicts": result["conflicts"] ?? 0
            ]
        } catch {
            return createErrorResponse(error.localizedDescription)
        }
    }
    
    // MARK: - Status
    
    /// Obtenir le statut de synchronisation
    ///
    /// - Returns: Dictionary {
    ///   pending_count: Int,
    ///   last_sync: String?,
    ///   is_syncing: Bool,
    ///   is_online: Bool,
    ///   connection_type: String
    /// }
    @objc public func getStatus(_ params: [String: Any]) -> [String: Any] {
        do {
            let result = try callPhpFunction(
                function: "OfflineSync::getStatus",
                params: [:]
            )
            
            return [
                "pending_count": result["pending_count"] ?? 0,
                "last_sync": result["last_sync"] as Any,
                "is_syncing": result["is_syncing"] ?? false,
                "is_online": connectivityMonitor.isOnline(),
                "connection_type": connectivityMonitor.getConnectionType()
            ]
        } catch {
            return createErrorResponse(error.localizedDescription)
        }
    }
    
    // MARK: - Monitoring
    
    /// Démarrer le monitoring de connectivité et auto-sync
    ///
    /// - Returns: Dictionary {success: Bool, monitoring: Bool}
    @objc public func startMonitoring(_ params: [String: Any]) -> [String: Any] {
        connectivityMonitor.startMonitoring { [weak self] isOnline in
            if isOnline {
                // Lancer une sync automatique quand la connexion revient
                self?.backgroundSync.scheduleSyncNow()
            }
        }
        
        return [
            "success": true,
            "monitoring": true
        ]
    }
    
    /// Arrêter le monitoring de connectivité
    ///
    /// - Returns: Dictionary {success: Bool, monitoring: Bool}
    @objc public func stopMonitoring(_ params: [String: Any]) -> [String: Any] {
        connectivityMonitor.stopMonitoring()
        
        return [
            "success": true,
            "monitoring": false
        ]
    }
    
    // MARK: - Background Sync
    
    /// Configurer la synchronisation périodique en arrière-plan
    ///
    /// - Parameter params: Dictionary contenant:
    ///   - interval_minutes: Int - intervalle en minutes (défaut: 30)
    ///   - require_wifi: Bool - sync uniquement en WiFi (défaut: false)
    /// - Returns: Dictionary {success: Bool}
    @objc public func schedulePeriodicSync(_ params: [String: Any]) -> [String: Any] {
        let intervalMinutes = params["interval_minutes"] as? Double ?? 30.0
        let requireWifi = params["require_wifi"] as? Bool ?? false
        
        backgroundSync.schedulePeriodicSync(
            intervalMinutes: intervalMinutes,
            requireWifi: requireWifi
        )
        
        return [
            "success": true,
            "interval_minutes": intervalMinutes,
            "require_wifi": requireWifi
        ]
    }
    
    /// Annuler la synchronisation périodique
    ///
    /// - Returns: Dictionary {success: Bool}
    @objc public func cancelPeriodicSync(_ params: [String: Any]) -> [String: Any] {
        backgroundSync.cancelPeriodicSync()
        
        return [
            "success": true
        ]
    }
    
    /// Planifier une sync immédiate
    ///
    /// - Returns: Dictionary {success: Bool}
    @objc public func scheduleSyncNow(_ params: [String: Any]) -> [String: Any] {
        backgroundSync.scheduleSyncNow()
        
        return [
            "success": true
        ]
    }
    
    // MARK: - Helper Methods
    
    /// Valider une opération
    private func isValidOperation(_ operation: String) -> Bool {
        return ["create", "update", "delete"].contains(operation)
    }
    
    /// Créer une réponse d'erreur standardisée
    private func createErrorResponse(_ message: String) -> [String: Any] {
        return [
            "success": false,
            "error": message
        ]
    }
    
    /// Appeler une fonction PHP via le bridge NativePHP
    ///
    /// IMPORTANT: Cette méthode dépend de l'implémentation du bridge NativePHP
    /// À adapter selon la véritable API du bridge
    private func callPhpFunction(function: String, params: [String: Any]) throws -> [String: Any] {
        // TODO: Remplacer par l'implémentation réelle du bridge NativePHP
        // Exemple hypothétique :
        // return try NativeBridge.call(function, params: params)
        
        // Pour l'instant, on lance une erreur
        throw NSError(
            domain: "OfflineSync",
            code: -1,
            userInfo: [NSLocalizedDescriptionKey: "NativePHP Bridge not implemented yet. Should call: \(function)"]
        )
    }
}
