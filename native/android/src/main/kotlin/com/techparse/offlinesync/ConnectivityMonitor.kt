package com.techparse.offlinesync

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import android.os.Build

/**
 * Moniteur de connectivité réseau
 * Détecte les changements de connexion (WiFi, Mobile, etc.)
 */
class ConnectivityMonitor(private val context: Context) {
    
    private val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
    private var networkCallback: ConnectivityManager.NetworkCallback? = null
    private var onConnectivityChanged: ((Boolean) -> Unit)? = null
    
    /**
     * Vérifier si le device est connecté à Internet
     */
    fun isOnline(): Boolean {
        return try {
            val network = connectivityManager.activeNetwork ?: return false
            val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
            
            capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
            capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
        } catch (e: Exception) {
            false
        }
    }
    
    /**
     * Vérifier si la connexion est en WiFi
     */
    fun isWifi(): Boolean {
        return try {
            val network = connectivityManager.activeNetwork ?: return false
            val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
            
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI)
        } catch (e: Exception) {
            false
        }
    }
    
    /**
     * Vérifier si la connexion est en données mobiles
     */
    fun isCellular(): Boolean {
        return try {
            val network = connectivityManager.activeNetwork ?: return false
            val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
            
            capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR)
        } catch (e: Exception) {
            false
        }
    }
    
    /**
     * Obtenir le type de connexion
     */
    fun getConnectionType(): String {
        return when {
            !isOnline() -> "offline"
            isWifi() -> "wifi"
            isCellular() -> "cellular"
            else -> "other"
        }
    }
    
    /**
     * Démarrer le monitoring de connectivité
     * 
     * @param callback Fonction appelée quand la connectivité change
     *                 Reçoit true si online, false si offline
     */
    fun startMonitoring(callback: (Boolean) -> Unit) {
        this.onConnectivityChanged = callback
        
        // Créer une requête réseau
        val request = NetworkRequest.Builder()
            .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .addCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
            .build()
        
        // Créer le callback
        networkCallback = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                super.onAvailable(network)
                callback(true)
            }
            
            override fun onLost(network: Network) {
                super.onLost(network)
                callback(false)
            }
            
            override fun onCapabilitiesChanged(
                network: Network,
                capabilities: NetworkCapabilities
            ) {
                super.onCapabilitiesChanged(network, capabilities)
                
                val isValidated = capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
                val hasInternet = capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                
                callback(isValidated && hasInternet)
            }
        }
        
        // Enregistrer le callback
        try {
            connectivityManager.registerNetworkCallback(request, networkCallback!!)
        } catch (e: Exception) {
            // En cas d'erreur, ne rien faire
        }
    }
    
    /**
     * Arrêter le monitoring
     */
    fun stopMonitoring() {
        networkCallback?.let { callback ->
            try {
                connectivityManager.unregisterNetworkCallback(callback)
            } catch (e: Exception) {
                // Ignore si déjà unregistered
            }
        }
        networkCallback = null
        onConnectivityChanged = null
    }
    
    /**
     * Obtenir des informations détaillées sur la connexion
     */
    fun getConnectionInfo(): Map<String, Any> {
        val info = mutableMapOf<String, Any>()
        
        info["is_online"] = isOnline()
        info["connection_type"] = getConnectionType()
        info["is_wifi"] = isWifi()
        info["is_cellular"] = isCellular()
        
        try {
            val network = connectivityManager.activeNetwork
            val capabilities = network?.let { connectivityManager.getNetworkCapabilities(it) }
            
            capabilities?.let { cap ->
                info["has_internet"] = cap.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                info["is_validated"] = cap.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
                info["link_downstream_bandwidth_kbps"] = cap.linkDownstreamBandwidthKbps
                info["link_upstream_bandwidth_kbps"] = cap.linkUpstreamBandwidthKbps
            }
        } catch (e: Exception) {
            // Ignorer les erreurs
        }
        
        return info
    }
    
    /**
     * Vérifier si la connexion est mesurée (données mobiles limitées)
     */
    fun isMeteredConnection(): Boolean {
        return try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                connectivityManager.isActiveNetworkMetered
            } else {
                // Sur les anciennes versions, considérer cellular comme metered
                isCellular()
            }
        } catch (e: Exception) {
            false
        }
    }
}
