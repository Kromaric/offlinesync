# Guide d'Installation - NativePHP Offline Sync & Backup

Guide complet pour installer et configurer le plugin OfflineSync dans votre application NativePHP Mobile.

---

## 📋 Prérequis

Avant de commencer, assurez-vous d'avoir :

### Environnement de développement

- ✅ **PHP** ≥ 8.1
- ✅ **Composer** (dernière version)
- ✅ **Laravel** ≥ 10.0
- ✅ **NativePHP** ≥ 0.8.0 installé
- ✅ **SQLite** (inclus avec PHP)

### Pour le développement mobile

#### Android
- ✅ Android Studio (dernière version)
- ✅ SDK Android API Level ≥ 24
- ✅ Kotlin 1.9.0+
- ✅ Gradle 8.0+

#### iOS
- ✅ Xcode 15.0+
- ✅ iOS 14.0+ (simulateur ou device)
- ✅ CocoaPods ou Swift Package Manager
- ✅ macOS (pour le développement iOS)

### Backend API
- ✅ Serveur Laravel accessible via HTTPS
- ✅ Base de données (MySQL, PostgreSQL, etc.)
- ✅ Laravel Sanctum (pour l'authentification)

---

## 🚀 Installation

### Étape 1 : Installation du plugin

#### Via Composer

```bash
composer require techparse/offline-sync
```

#### Vérification de l'installation

```bash
composer show techparse/offline-sync
```

Vous devriez voir la version installée et les dépendances.

---

### Étape 2 : Enregistrement du plugin

Enregistrez le plugin auprès de NativePHP :

```bash
php artisan native:plugin:register techparse/offline-sync
```

Cette commande :
- ✅ Enregistre le plugin dans NativePHP
- ✅ Copie les bridges natifs (Kotlin/Swift)
- ✅ Configure les permissions

---

### Étape 3 : Publication de la configuration

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=offline-sync-config
```

Cela créera le fichier `config/offline-sync.php`.

---

### Étape 4 : Configuration de base

Éditez le fichier `.env` :

```env
# API Backend URL
SYNC_API_URL=https://api.votre-app.com

# Méthode d'authentification (bearer ou api_key)
SYNC_AUTH_METHOD=bearer

# Token API (si SYNC_AUTH_METHOD=api_key)
SYNC_API_TOKEN=votre-token-secret

# Sécurité
SYNC_ENCRYPT_QUEUE=true
SYNC_REQUIRE_HTTPS=true

# Performance
SYNC_BATCH_SIZE=50
SYNC_MAX_QUEUE=1000

# Connectivité
SYNC_AUTO_SYNC=true
SYNC_REQUIRE_WIFI=false

# Retry
SYNC_MAX_RETRIES=3
SYNC_RETRY_DELAY=60

# Logging
SYNC_LOGGING=true
SYNC_LOG_CHANNEL=daily
```

---

### Étape 5 : Exécution des migrations

Créez les tables de la base de données :

```bash
php artisan migrate
```

Cela créera :
- ✅ `offline_sync_queue` - File d'attente de synchronisation
- ✅ `offline_sync_logs` - Historique des synchronisations

#### Vérification

```bash
php artisan migrate:status
```

Vous devriez voir :
```
Ran? Migration
Yes  2025_01_01_000001_create_offline_sync_queue_table
Yes  2025_01_01_000002_create_offline_sync_logs_table
```

---

### Étape 6 : Configuration des modèles

Ajoutez le trait `Syncable` à vos modèles Eloquent :

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Techparse\OfflineSync\Traits\Syncable;

class Task extends Model
{
    use Syncable;
    
    protected $fillable = ['title', 'description', 'completed'];
    
    // Optionnel : personnaliser le nom de la ressource
    protected $syncResourceName = 'tasks';
    
    // Optionnel : exclure certains champs de la sync
    protected $syncExcluded = ['internal_notes', 'admin_only'];
}
```

---

### Étape 7 : Mapping des ressources

Éditez `config/offline-sync.php` :

```php
'resource_mapping' => [
    'tasks' => \App\Models\Task::class,
    'users' => \App\Models\User::class,
    'projects' => \App\Models\Project::class,
    // Ajoutez vos autres modèles...
],
```

---

### Étape 8 : Configuration des conflits

Configurez les stratégies de résolution de conflits :

```php
'conflict_resolution' => [
    // Stratégie par défaut pour toutes les ressources
    'default_strategy' => 'last_write_wins',
    
    // Stratégies spécifiques par ressource
    'per_resource' => [
        'tasks' => 'last_write_wins',
        'users' => 'server_wins',      // Les données utilisateur du serveur prioritaires
        'settings' => 'client_wins',   // Les paramètres locaux prioritaires
        'notes' => 'merge',            // Fusion intelligente
    ],
],
```

**Stratégies disponibles :**
- `server_wins` - Le serveur gagne toujours
- `client_wins` - Le client gagne toujours
- `last_write_wins` - Le plus récent gagne
- `merge` - Fusion intelligente des champs

---

## 🔧 Configuration Backend

### Étape 9 : Installation de Sanctum (si pas déjà fait)

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### Étape 10 : Configuration des routes API

Ajoutez dans `routes/api.php` :

```php
use Techparse\OfflineSync\Http\Controllers\SyncController;

Route::middleware('auth:sanctum')->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push']);
    Route::get('/pull/{resource}', [SyncController::class, 'pull']);
    Route::get('/status', [SyncController::class, 'status']);
    Route::get('/ping', [SyncController::class, 'ping']);
});
```

### Étape 11 : Génération de tokens

Pour chaque utilisateur de l'app mobile :

```php
// Dans votre controller de login
$user = User::find(1);
$token = $user->createToken('mobile-app')->plainTextToken;

// Stocker ce token dans l'app mobile
return response()->json([
    'token' => $token,
    'user' => $user,
]);
```

---

## 📱 Configuration Mobile

### Android

Le plugin copie automatiquement les fichiers Kotlin nécessaires.

#### Vérification

Vérifiez que ces fichiers existent dans votre projet Android :

```
android/app/src/main/kotlin/com/vendor/offlinesync/
├── OfflineSyncFunctions.kt
├── ConnectivityMonitor.kt
└── BackgroundSyncWorker.kt
```

#### Permissions

Vérifiez dans `AndroidManifest.xml` :

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.ACCESS_WIFI_STATE" />
```

### iOS

#### Vérification

Vérifiez que ces fichiers existent :

```
ios/Sources/OfflineSync/
├── OfflineSyncFunctions.swift
├── ConnectivityMonitor.swift
└── BackgroundSyncScheduler.swift
```

#### Configuration Info.plist

Ajoutez dans `Info.plist` :

```xml
<key>NSLocalNetworkUsageDescription</key>
<string>Cette app a besoin d'accéder au réseau pour synchroniser vos données.</string>

<key>UIBackgroundModes</key>
<array>
    <string>fetch</string>
    <string>processing</string>
</array>
```

---

## ✅ Vérification de l'installation

### Test 1 : Vérifier les commandes Artisan

```bash
php artisan list sync
```

Vous devriez voir :
```
sync:clear   Clear sync queue
sync:pull    Pull remote changes from the server
sync:push    Push local changes to the server
sync:status  Show sync queue status
```

### Test 2 : Créer un item de test

```php
// Dans tinker ou un controller
php artisan tinker

>>> $task = \App\Models\Task::create(['title' => 'Test Sync']);
>>> \Techparse\OfflineSync\Facades\OfflineSync::getPending()->count();
=> 1
```

### Test 3 : Vérifier le statut

```bash
php artisan sync:status
```

Vous devriez voir votre item en attente.

### Test 4 : Tester la connexion backend

```bash
curl https://api.votre-app.com/api/sync/ping \
  -H "Authorization: Bearer votre-token"
```

Réponse attendue :
```json
{"status":"ok"}
```

---

## 🔍 Dépannage

### Problème : "Class OfflineSync not found"

**Solution :**
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Problème : Migrations échouent

**Solution :**
```bash
# Rollback et réessayer
php artisan migrate:rollback
php artisan migrate
```

### Problème : Routes API non accessibles

**Vérifiez :**
1. Sanctum est installé : `composer show laravel/sanctum`
2. Token est valide
3. CORS est configuré (si frontend séparé)

### Problème : Items ne se synchronisent pas

**Vérifiez :**
1. La connectivité : `php artisan sync:status`
2. Les logs : `storage/logs/laravel.log`
3. La configuration de l'API : `.env` → `SYNC_API_URL`

### Problème : Erreur HTTPS required

**Solution :**
```env
# Pour développement local uniquement
SYNC_REQUIRE_HTTPS=false

# En production, TOUJOURS utiliser HTTPS
SYNC_REQUIRE_HTTPS=true
```

---

## 🎓 Prochaines étapes

Une fois l'installation terminée :

1. ✅ **Lire le guide Backend** : [BACKEND.md](BACKEND.md)
2. ✅ **Configurer les conflits** : [CONFLICTS.md](CONFLICTS.md)
3. ✅ **Sécuriser l'app** : [SECURITY.md](SECURITY.md)
4. ✅ **Utiliser l'API** : Voir [README.md](../README.md)

---

## 📞 Support

Besoin d'aide ?

- 📧 Email : support@techparse.fr
- 📖 Documentation : https://docs.techparse.fr/offline-sync
- 🐛 Issues : https://github.com/Kromaric/offline-sync/issues

---

## 🎉 Installation réussie !

Votre plugin OfflineSync est maintenant installé et configuré.

**Commencez à synchroniser :**

```php
use Techparse\OfflineSync\Facades\OfflineSync;

// Créer des données (automatiquement queued)
$task = Task::create(['title' => 'Ma première tâche']);

// Synchroniser
OfflineSync::sync(['tasks']);

// Vérifier
$status = OfflineSync::getStatus();
```

Bon développement ! 🚀
