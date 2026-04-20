package com.vendor.offlinesync.examples

import android.content.Context
import com.vendor.offlinesync.OfflineSyncFunctions
import com.vendor.offlinesync.ConnectivityMonitor
import com.vendor.offlinesync.BackgroundSyncWorker
import org.json.JSONObject
import org.json.JSONArray

/**
 * Exemples d'utilisation du plugin OfflineSync
 */
class OfflineSyncExamples(private val context: Context) {
    
    private val syncFunctions = OfflineSyncFunctions(context)
    private val connectivityMonitor = ConnectivityMonitor(context)
    private val backgroundSync = BackgroundSyncWorker.getInstance(context)
    
    /**
     * Exemple 1: Ajouter un item à la queue
     */
    fun queueExample() {
        val params = JSONObject().apply {
            put("resource", "tasks")
            put("resource_id", "123")
            put("operation", "update")
            put("data", JSONObject().apply {
                put("title", "Ma tâche")
                put("completed", false)
            })
            put("timestamp", "2025-02-06T10:30:00Z")
        }
        
        syncFunctions.queueAction(params).thenAccept { result ->
            if (result.optBoolean("success", false)) {
                val queueId = result.optInt("queue_id")
                println("Item queued with ID: $queueId")
            } else {
                val error = result.optString("error")
                println("Error: $error")
            }
        }
    }
    
    /**
     * Exemple 2: Synchroniser manuellement
     */
    fun syncExample() {
        val params = JSONObject().apply {
            put("resources", JSONArray().apply {
                put("tasks")
                put("users")
            })
        }
        
        syncFunctions.runSync(params).thenAccept { result ->
            if (result.optBoolean("success", false)) {
                println("Synced: ${result.optInt("synced")}")
                println("Failed: ${result.optInt("failed")}")
                println("Conflicts: ${result.optInt("conflicts")}")
            } else {
                println("Sync failed: ${result.optString("error")}")
            }
        }
    }
    
    /**
     * Exemple 3: Obtenir le statut
     */
    fun statusExample() {
        syncFunctions.getStatus(JSONObject()).thenAccept { result ->
            println("Pending items: ${result.optInt("pending_count")}")
            println("Last sync: ${result.optString("last_sync")}")
            println("Is syncing: ${result.optBoolean("is_syncing")}")
            println("Is online: ${result.optBoolean("is_online")}")
            println("Connection: ${result.optString("connection_type")}")
        }
    }
    
    /**
     * Exemple 4: Démarrer le monitoring de connectivité
     */
    fun startMonitoringExample() {
        syncFunctions.startMonitoring(JSONObject()).thenAccept { result ->
            if (result.optBoolean("success", false)) {
                println("Monitoring started")
            }
        }
    }
    
    /**
     * Exemple 5: Vérifier la connectivité directement
     */
    fun checkConnectivityExample() {
        println("Is online: ${connectivityMonitor.isOnline()}")
        println("Is WiFi: ${connectivityMonitor.isWifi()}")
        println("Is cellular: ${connectivityMonitor.isCellular()}")
        println("Connection type: ${connectivityMonitor.getConnectionType()}")
        println("Is metered: ${connectivityMonitor.isMeteredConnection()}")
        
        val info = connectivityMonitor.getConnectionInfo()
        println("Detailed info: $info")
    }
    
    /**
     * Exemple 6: Planifier une sync périodique
     */
    fun schedulePeriodicSyncExample() {
        val params = JSONObject().apply {
            put("interval_minutes", 30)
            put("require_wifi", true)
        }
        
        syncFunctions.schedulePeriodicSync(params).thenAccept { result ->
            if (result.optBoolean("success", false)) {
                println("Periodic sync scheduled")
                println("Interval: ${result.optInt("interval_minutes")} minutes")
                println("Require WiFi: ${result.optBoolean("require_wifi")}")
            }
        }
    }
    
    /**
     * Exemple 7: Sync immédiate en arrière-plan
     */
    fun immediateSyncExample() {
        backgroundSync.scheduleSyncNow()
        println("Immediate sync scheduled")
    }
    
    /**
     * Exemple 8: Monitoring avec callback
     */
    fun monitoringWithCallbackExample() {
        connectivityMonitor.startMonitoring { isOnline ->
            if (isOnline) {
                println("✅ Connection restored - triggering sync")
                backgroundSync.scheduleSyncNow()
            } else {
                println("❌ Connection lost")
            }
        }
    }
    
    /**
     * Exemple 9: Obtenir le statut des workers
     */
    fun workerStatusExample() {
        val status = backgroundSync.getSyncWorkersStatus()
        println("Periodic worker: ${status["periodic"]}")
        println("One-time worker: ${status["one_time"]}")
    }
    
    /**
     * Exemple 10: Annuler toutes les syncs
     */
    fun cancelAllSyncExample() {
        backgroundSync.cancelAllSync()
        connectivityMonitor.stopMonitoring()
        println("All syncs cancelled and monitoring stopped")
    }
    
    /**
     * Exemple complet: Configuration initiale de l'app
     */
    fun completeSetupExample() {
        // 1. Démarrer le monitoring
        connectivityMonitor.startMonitoring { isOnline ->
            if (isOnline) {
                println("Online - syncing...")
                backgroundSync.scheduleSyncNow()
            } else {
                println("Offline - will sync when connection is restored")
            }
        }
        
        // 2. Planifier sync périodique (toutes les 30min, WiFi uniquement)
        backgroundSync.schedulePeriodicSync(
            intervalMinutes = 30,
            requireWifi = true
        )
        
        // 3. Sync initiale si online
        if (connectivityMonitor.isOnline()) {
            backgroundSync.scheduleSyncNow()
        }
        
        println("OfflineSync setup complete!")
    }
    
    /**
     * Exemple: Cleanup lors de la fermeture de l'app
     */
    fun cleanupExample() {
        // Arrêter le monitoring
        connectivityMonitor.stopMonitoring()
        
        // Note: Les workers périodiques continuent même après fermeture
        // Pour les annuler complètement:
        // backgroundSync.cancelPeriodicSync()
        
        println("Cleanup done")
    }
}
