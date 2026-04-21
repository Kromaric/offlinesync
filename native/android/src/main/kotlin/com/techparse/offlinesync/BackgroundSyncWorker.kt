package com.techparse.offlinesync

import android.content.Context
import androidx.work.*
import org.json.JSONObject
import java.util.concurrent.TimeUnit

/**
 * Worker pour la synchronisation en arrière-plan
 * Utilise WorkManager pour planifier des syncs même quand l'app est fermée
 */
class BackgroundSyncWorker(
    context: Context,
    params: WorkerParameters
) : Worker(context, params) {
    
    companion object {
        private const val WORK_NAME_PERIODIC = "offline_sync_periodic"
        private const val WORK_NAME_ONE_TIME = "offline_sync_one_time"
        
        @Volatile
        private var instance: BackgroundSyncWorker? = null
        
        fun getInstance(context: Context): BackgroundSyncWorker {
            return instance ?: synchronized(this) {
                instance ?: BackgroundSyncWorker(
                    context,
                    WorkerParameters(
                        java.util.UUID.randomUUID(),
                        Data.EMPTY,
                        emptyList(),
                        WorkerParameters.RuntimeExtras()
                    )
                ).also { instance = it }
            }
        }
    }
    
    /**
     * Exécuter la synchronisation
     */
    override fun doWork(): Result {
        return try {
            // Appeler la fonction de sync via le bridge
            val syncFunctions = OfflineSyncFunctions(applicationContext)
            val params = JSONObject()
            
            // Exécuter la sync et attendre le résultat
            val result = syncFunctions.runSync(params).get()
            
            if (result.optBoolean("success", false)) {
                // Sync réussie
                val synced = result.optInt("synced", 0)
                val failed = result.optInt("failed", 0)
                
                if (failed > 0) {
                    // Quelques échecs, on réessaiera
                    Result.retry()
                } else {
                    Result.success()
                }
            } else {
                // Erreur de sync
                val error = result.optString("error", "Unknown error")
                
                // Créer des output data pour le log
                val outputData = Data.Builder()
                    .putString("error", error)
                    .build()
                
                Result.failure(outputData)
            }
        } catch (e: Exception) {
            // Exception pendant la sync
            val outputData = Data.Builder()
                .putString("error", e.message ?: "Unknown exception")
                .build()
            
            Result.failure(outputData)
        }
    }
    
    /**
     * Planifier une synchronisation immédiate
     */
    fun scheduleSyncNow() {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()
        
        val syncRequest = OneTimeWorkRequestBuilder<BackgroundSyncWorker>()
            .setConstraints(constraints)
            .addTag(WORK_NAME_ONE_TIME)
            .build()
        
        WorkManager.getInstance(applicationContext).enqueueUniqueWork(
            WORK_NAME_ONE_TIME,
            ExistingWorkPolicy.REPLACE,
            syncRequest
        )
    }
    
    /**
     * Planifier une synchronisation périodique
     * 
     * @param intervalMinutes Intervalle en minutes entre chaque sync
     * @param requireWifi Si true, sync uniquement en WiFi
     */
    fun schedulePeriodicSync(
        intervalMinutes: Long = 30,
        requireWifi: Boolean = false
    ) {
        val networkType = if (requireWifi) {
            NetworkType.UNMETERED // WiFi ou Ethernet
        } else {
            NetworkType.CONNECTED // N'importe quelle connexion
        }
        
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(networkType)
            .setRequiresBatteryNotLow(true) // Ne pas drainer la batterie
            .build()
        
        val syncRequest = PeriodicWorkRequestBuilder<BackgroundSyncWorker>(
            intervalMinutes,
            TimeUnit.MINUTES
        )
            .setConstraints(constraints)
            .addTag(WORK_NAME_PERIODIC)
            .setBackoffCriteria(
                BackoffPolicy.EXPONENTIAL,
                WorkRequest.MIN_BACKOFF_MILLIS,
                TimeUnit.MILLISECONDS
            )
            .build()
        
        WorkManager.getInstance(applicationContext).enqueueUniquePeriodicWork(
            WORK_NAME_PERIODIC,
            ExistingPeriodicWorkPolicy.UPDATE,
            syncRequest
        )
    }
    
    /**
     * Annuler la synchronisation périodique
     */
    fun cancelPeriodicSync() {
        WorkManager.getInstance(applicationContext)
            .cancelUniqueWork(WORK_NAME_PERIODIC)
    }
    
    /**
     * Annuler toutes les synchronisations
     */
    fun cancelAllSync() {
        WorkManager.getInstance(applicationContext).apply {
            cancelUniqueWork(WORK_NAME_PERIODIC)
            cancelUniqueWork(WORK_NAME_ONE_TIME)
        }
    }
    
    /**
     * Obtenir le statut des workers
     */
    fun getSyncWorkersStatus(): Map<String, String> {
        val workManager = WorkManager.getInstance(applicationContext)
        val status = mutableMapOf<String, String>()
        
        try {
            // Statut du worker périodique
            val periodicInfo = workManager.getWorkInfosForUniqueWork(WORK_NAME_PERIODIC).get()
            status["periodic"] = if (periodicInfo.isNotEmpty()) {
                periodicInfo[0].state.name
            } else {
                "NOT_SCHEDULED"
            }
            
            // Statut du worker one-time
            val oneTimeInfo = workManager.getWorkInfosForUniqueWork(WORK_NAME_ONE_TIME).get()
            status["one_time"] = if (oneTimeInfo.isNotEmpty()) {
                oneTimeInfo[0].state.name
            } else {
                "NOT_SCHEDULED"
            }
        } catch (e: Exception) {
            status["error"] = e.message ?: "Unknown error"
        }
        
        return status
    }
}
