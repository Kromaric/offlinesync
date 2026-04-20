# 📝 Todo App - Démo OfflineSync

Application démo complète montrant l'utilisation du plugin OfflineSync.

---

## 🎯 Fonctionnalités

✅ **CRUD complet** : Créer, lire, modifier, supprimer des tâches
✅ **Mode offline** : Fonctionne sans connexion
✅ **Sync automatique** : Synchronisation bidirectionnelle
✅ **Gestion conflits** : Stratégie Last Write Wins
✅ **UI moderne** : Interface responsive et élégante
✅ **Stats temps réel** : Compteurs de tâches
✅ **Priorités** : Haute, Moyenne, Basse
✅ **Dates d'échéance** : Avec détection des retards
✅ **Notifications** : Feedback utilisateur

---

## 📁 Structure

```
demo/
├── backend/                    # Code Laravel backend
│   ├── Task.php               # Model avec trait Syncable
│   ├── TaskController.php     # CRUD API
│   ├── AuthController.php     # Authentification
│   ├── api.php                # Routes API
│   ├── TaskSeeder.php         # Données de test
│   └── config-offline-sync.php
│
└── mobile/                     # App mobile (HTML/JS)
    ├── index.html             # Interface utilisateur
    └── app.js                 # Logique JavaScript
```

---

## 🚀 Installation Backend

### 1. Configuration

```env
# .env
SYNC_API_URL=https://api.todo-demo.test
SYNC_AUTH_METHOD=bearer
SYNC_REQUIRE_HTTPS=true
SYNC_ENCRYPT_QUEUE=true
```

### 2. Migrations et seed

```bash
php artisan migrate
php artisan db:seed --class=TaskSeeder
```

Cela créera :
- User : demo@example.com / password
- 6 tâches de test (dont 1 en retard)

### 3. Lancer le serveur

```bash
php artisan serve
```

API disponible à : http://localhost:8000

---

## 📱 Installation Mobile

### Option A : Ouvrir directement (démo front-end)

```bash
# Ouvrir dans le navigateur
open demo/mobile/index.html
```

### Option B : Intégrer dans NativePHP

```php
// Dans votre app NativePHP Mobile
return view('todo-app'); // Blade template avec le HTML
```

---

## 🧪 Tester la Démo

### 1. Créer une tâche online

1. Ouvrir l'app mobile
2. Remplir le formulaire "Ajouter une tâche"
3. Cliquer sur "Ajouter"
4. ✅ La tâche apparaît immédiatement
5. ✅ Elle est sauvegardée sur le serveur

### 2. Créer une tâche offline

1. **Désactiver la connexion** (Dev Tools → Network → Offline)
2. Ajouter une nouvelle tâche
3. ✅ La tâche apparaît avec badge "⏳ En attente"
4. ✅ Compteur "en attente" augmente
5. **Réactiver la connexion**
6. Cliquer sur "🔄 Synchroniser"
7. ✅ La tâche est envoyée au serveur
8. ✅ Badge "En attente" disparaît

### 3. Modifier une tâche

1. Cocher/décocher une tâche
2. ✅ Changement instantané dans l'UI
3. ✅ Sauvegardé sur le serveur (ou queueé si offline)

### 4. Tester les conflits

1. Ouvrir l'app dans 2 onglets (User A et User B)
2. Passer l'onglet A en offline
3. Modifier la même tâche dans A (offline) et B (online)
4. Reconnecter A et synchroniser
5. ✅ La stratégie Last Write Wins s'applique
6. ✅ La version la plus récente gagne

### 5. Supprimer une tâche

1. Cliquer sur 🗑️
2. Confirmer
3. ✅ Tâche disparaît immédiatement
4. ✅ Suppression synchronisée

---

## 🎨 Interface Utilisateur

### Header
- **Statut** : Online/Offline avec indicateur coloré
- **En attente** : Nombre d'opérations en queue
- **Stats** : Total / Complétées / En cours

### Formulaire
- Titre (requis)
- Description (optionnel)
- Priorité (Basse/Moyenne/Haute)
- Date d'échéance (optionnel)

### Liste de tâches
- Checkbox pour compléter
- Badge priorité (🔴🟠🟢)
- Badge date (📅 ou ⚠️ si retard)
- Badge "En attente" si non sync
- Bouton supprimer 🗑️

---

## 🔧 Personnalisation

### Modifier les couleurs

```css
/* Dans index.html */
:root {
    --primary: #667eea;    /* Couleur principale */
    --success: #48bb78;    /* Vert success */
    --danger: #f56565;     /* Rouge danger */
}
```

### Changer la stratégie de conflits

```php
// config/offline-sync.php
'per_resource' => [
    'tasks' => 'client_wins', // Le client gagne toujours
    // ou 'server_wins', 'last_write_wins', 'merge'
],
```

### Ajouter des champs

1. Modifier la migration (ajouter colonne)
2. Ajouter au `$fillable` dans Task.php
3. Ajouter dans le formulaire HTML
4. Envoyer dans le JavaScript

---

## 📊 API Endpoints

### Auth
- `POST /api/register` - Créer compte
- `POST /api/login` - Connexion
- `POST /api/logout` - Déconnexion
- `GET /api/me` - User actuel

### Tasks
- `GET /api/tasks` - Liste tâches
- `POST /api/tasks` - Créer tâche
- `GET /api/tasks/{id}` - Détail tâche
- `PUT /api/tasks/{id}` - Modifier tâche
- `DELETE /api/tasks/{id}` - Supprimer tâche
- `POST /api/tasks/{id}/toggle` - Toggle complété
- `GET /api/tasks-stats` - Statistiques

### Sync
- `POST /api/sync/push` - Push changements
- `GET /api/sync/pull/tasks` - Pull changements
- `GET /api/sync/status` - Statut sync
- `GET /api/sync/ping` - Health check

---

## 🐛 Debugging

### Vérifier les logs Laravel

```bash
tail -f storage/logs/laravel.log
```

### Console JavaScript

```javascript
// Activer les logs OfflineSync
localStorage.setItem('debug_sync', 'true');
```

### Tester l'API avec cURL

```bash
# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@example.com","password":"password"}'

# Créer une tâche
curl -X POST http://localhost:8000/api/tasks \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test task","priority":"high"}'
```

---

## 🎓 Apprentissage

### Concepts démontrés

1. **Trait Syncable** : Auto-queue des opérations
2. **API RESTful** : Endpoints CRUD complets
3. **Authentification** : Laravel Sanctum
4. **Offline-first** : L'app fonctionne sans connexion
5. **Sync bidirectionnelle** : Push et Pull
6. **Gestion conflits** : Last Write Wins
7. **UI responsive** : Mobile-friendly
8. **Feedback utilisateur** : Notifications

### Code à étudier

- **Task.php** : Utilisation du trait Syncable
- **TaskController.php** : Validation et business logic
- **app.js** : Gestion offline/online
- **config-offline-sync.php** : Configuration complète

---

## 📈 Évolutions Possibles

- [ ] Catégories de tâches
- [ ] Tags
- [ ] Pièces jointes
- [ ] Rappels
- [ ] Partage de tâches
- [ ] Mode sombre
- [ ] Filtres avancés
- [ ] Export PDF
- [ ] Statistiques détaillées
- [ ] Calendrier vue

---

## 📞 Support

Questions sur la démo ?
- 📧 Email : demo@vendorname.com
- 📖 Docs : Voir ../docs/

---

**Profitez de la démo !** 🚀
