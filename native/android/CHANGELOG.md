# Changelog - Android Native Code

## v1.0.0 (2025-02-06)

### ✨ Features

#### OfflineSyncFunctions.kt
- ✅ `queueAction()` - Queue des opérations pour sync
- ✅ `runSync()` - Synchronisation manuelle
- ✅ `getStatus()` - Statut de la queue et connexion
- ✅ `startMonitoring()` - Démarrage du monitoring
- ✅ `stopMonitoring()` - Arrêt du monitoring
- ✅ `schedulePeriodicSync()` - Planification sync périodique
- ✅ `cancelPeriodicSync()` - Annulation sync périodique

#### ConnectivityMonitor.kt
- ✅ Détection WiFi / Données mobiles
- ✅ Monitoring temps réel des changements de connexion
- ✅ Informations détaillées (bande passante, type)
- ✅ Détection des connexions mesurées (limited data)
- ✅ Support Android 7.0+ (API 24+)

#### BackgroundSyncWorker.kt
- ✅ Synchronisation en arrière-plan avec WorkManager
- ✅ Sync périodique configurable
- ✅ Contraintes réseau (WiFi only, connected, etc.)
- ✅ Contrainte batterie (ne pas drainer)
- ✅ Retry automatique avec backoff exponentiel
- ✅ Persistance après reboot
- ✅ Respect du Doze mode et App Standby

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

### 📝 Documentation
- README.md avec exemples complets
- Fichier d'exemples (OfflineSyncExamples.kt)
- ProGuard rules pour release
- Configuration Gradle

### ⚠️ Known Issues
- `callPhpFunction()` nécessite implémentation du bridge NativePHP réel
- Tests unitaires à ajouter

### 🔜 Planned for v1.1.0
- Tests unitaires avec JUnit
- Tests instrumentés avec Espresso
- Support des notifications de sync
- Statistiques de sync détaillées
- Mode debug avec logs verbeux
