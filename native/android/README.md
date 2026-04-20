# OfflineSync - Code Natif Android

Ce dossier contient le code natif Android (Kotlin) pour le plugin OfflineSync.

## 📁 Structure

```
android/
├── build.gradle                    # Configuration Gradle
├── src/
│   └── main/
│       ├── AndroidManifest.xml     # Permissions
│       └── kotlin/
│           └── com/vendor/offlinesync/
│               ├── OfflineSyncFunctions.kt      # Bridge functions principales
│               ├── ConnectivityMonitor.kt       # Monitoring de connexion
│               └── BackgroundSyncWorker.kt      # Sync en arrière-plan
```

## 🔧 Composants

### OfflineSyncFunctions.kt
Bridge entre le code PHP/Laravel et Android. Expose les fonctions :
- `queueAction()` - Ajouter une action à la queue
- `runSync()` - Lancer une synchronisation
- `getStatus()` - Obtenir le statut
- `startMonitoring()` - Démarrer le monitoring
- `stopMonitoring()` - Arrêter le monitoring
- `schedulePeriodicSync()` - Planifier une sync périodique
- `cancelPeriodicSync()` - Annuler la sync périodique

### ConnectivityMonitor.kt
Détection de la connectivité réseau :
- Détecte WiFi / Données mobiles
- Écoute les changements de connexion
- Fournit des infos détaillées (bande passante, etc.)
- Détecte les connexions mesurées (limited data)

### BackgroundSyncWorker.kt
Synchronisation en arrière-plan avec WorkManager :
- Sync périodique même quand l'app est fermée
- Contraintes configurables (WiFi only, batterie, etc.)
- Retry automatique en cas d'échec
- Politique de backoff exponentiel

## 📦 Dépendances

```gradle
// WorkManager pour background sync
implementation 'androidx.work:work-runtime-ktx:2.9.0'

// OkHttp pour requêtes HTTP
implementation 'com.squareup.okhttp3:okhttp:4.12.0'

// Coroutines (optionnel)
implementation 'org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3'
```

## 🔐 Permissions requises

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.ACCESS_WIFI_STATE" />
<uses-permission android:name="android.permission.CHANGE_NETWORK_STATE" />
```

## 🚀 Utilisation

### Depuis le code PHP/NativePHP

Les fonctions sont appelées automatiquement via le bridge NativePHP :

```php
// Côté PHP
OfflineSync::queue($model, 'create');
```

Le bridge NativePHP appelle automatiquement :

```kotlin
// Côté Android
OfflineSyncFunctions.queueAction(params)
```

### Configuration de la sync périodique

```php
// Activer la sync toutes les 30 minutes, WiFi uniquement
NativeBridge::call('OfflineSync.schedulePeriodicSync', [
    'interval_minutes' => 30,
    'require_wifi' => true
]);
```

## 📱 Versions supportées

- **Min SDK**: 24 (Android 7.0 Nougat)
- **Target SDK**: 34 (Android 14)
- **Compile SDK**: 34

## ⚡ WorkManager

Le plugin utilise WorkManager pour garantir :
- Exécution même si l'app est tuée
- Respect des contraintes (WiFi, batterie, etc.)
- Retry automatique avec backoff
- Persistance des tâches après reboot

## 🔄 Flux de synchronisation

```
1. Changement de données → Queue locale (SQLite)
2. ConnectivityMonitor détecte connexion
3. BackgroundSyncWorker déclenché
4. OfflineSyncFunctions.runSync() appelé
5. Bridge → Code PHP → API Laravel
6. Résolution des conflits
7. Mise à jour locale
```

## 🧪 Tests

Pour tester la connectivité :

```kotlin
val monitor = ConnectivityMonitor(context)
println("Online: ${monitor.isOnline()}")
println("Type: ${monitor.getConnectionType()}")
println("Info: ${monitor.getConnectionInfo()}")
```

Pour tester le background sync :

```kotlin
val worker = BackgroundSyncWorker.getInstance(context)
worker.scheduleSyncNow()
```

## 🐛 Debugging

Activer les logs WorkManager :

```kotlin
WorkManager.getInstance(context).apply {
    val config = Configuration.Builder()
        .setMinimumLoggingLevel(android.util.Log.DEBUG)
        .build()
}
```

## ⚠️ Important

Le fichier `OfflineSyncFunctions.kt` contient une méthode `callPhpFunction()` qui doit être implémentée selon l'API réelle du bridge NativePHP. Actuellement, elle lance une `NotImplementedError`.

## 📝 Notes

- Les Workers sont persistés même après reboot du device
- La sync périodique s'adapte aux contraintes système (Doze mode, App Standby)
- En WiFi metered, le système peut retarder la sync
- Les contraintes de batterie sont respectées automatiquement
