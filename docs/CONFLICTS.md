# Guide Résolution de Conflits

Guide complet pour comprendre et configurer la résolution de conflits dans OfflineSync.

---

## 📋 Table des matières

1. [Qu'est-ce qu'un conflit ?](#quest-ce-quun-conflit-)
2. [Les 4 stratégies](#les-4-stratégies)
3. [Configuration](#configuration)
4. [Cas d'usage](#cas-dusage)
5. [Stratégies personnalisées](#stratégies-personnalisées)
6. [Debugging](#debugging)

---

## ❓ Qu'est-ce qu'un conflit ?

Un conflit se produit quand **les mêmes données** ont été modifiées à la fois **localement** (sur l'app mobile) et **sur le serveur** depuis la dernière synchronisation.

### Exemple de conflit

```
Situation initiale :
┌─────────────────────┬─────────────────────┐
│ App Mobile          │ Serveur            │
├─────────────────────┼─────────────────────┤
│ Task #1             │ Task #1            │
│ title: "Acheter"    │ title: "Acheter"   │
│ updated: 10:00      │ updated: 10:00     │
└─────────────────────┴─────────────────────┘

Mode Offline - L'utilisateur modifie sur mobile :
┌─────────────────────┬─────────────────────┐
│ App Mobile          │ Serveur            │
├─────────────────────┼─────────────────────┤
│ Task #1             │ Task #1            │
│ title: "Acheter 🍎" │ title: "Acheter"   │
│ updated: 10:30      │ updated: 10:00     │
└─────────────────────┴─────────────────────┘

Pendant ce temps, un collègue modifie sur le serveur :
┌─────────────────────┬─────────────────────┐
│ App Mobile          │ Serveur            │
├─────────────────────┼─────────────────────┤
│ Task #1             │ Task #1            │
│ title: "Acheter 🍎" │ title: "Acheter 🍞"│
│ updated: 10:30      │ updated: 10:25     │
└─────────────────────┴─────────────────────┘

❌ CONFLIT lors de la synchronisation !
Quelle version garder ? 🍎 ou 🍞 ?
```

---

## 🎯 Les 4 Stratégies

OfflineSync propose 4 stratégies prêtes à l'emploi :

### 1. Server Wins (Le serveur gagne)

**Comportement :** Les données du serveur **écrasent toujours** les données locales.

**Avantages :**
- ✅ Simple et prévisible
- ✅ Le serveur reste la source de vérité
- ✅ Aucune perte de données serveur

**Inconvénients :**
- ❌ Les modifications locales sont perdues
- ❌ Frustrant pour l'utilisateur

**Quand l'utiliser :**
- Données critiques (finances, inventaire)
- Informations système (paramètres globaux)
- Données partagées importantes

**Exemple :**

```php
'conflict_resolution' => [
    'per_resource' => [
        'users' => 'server_wins',      // Profils utilisateurs
        'prices' => 'server_wins',      // Prix des produits
        'inventory' => 'server_wins',   // Stock
    ],
],
```

**Résultat du conflit :**
```
Avant sync : Mobile = "Acheter 🍎" / Serveur = "Acheter 🍞"
Après sync : Mobile = "Acheter 🍞" / Serveur = "Acheter 🍞"
✅ Le serveur a gagné
```

---

### 2. Client Wins (Le client gagne)

**Comportement :** Les données locales **écrasent toujours** les données du serveur.

**Avantages :**
- ✅ Les modifications locales sont toujours préservées
- ✅ Bonne UX pour l'utilisateur final
- ✅ Utile pour les préférences personnelles

**Inconvénients :**
- ❌ Peut écraser des données serveur importantes
- ❌ Risque de conflits avec d'autres utilisateurs

**Quand l'utiliser :**
- Préférences utilisateur personnelles
- Paramètres locaux
- Brouillons et notes privées

**Exemple :**

```php
'conflict_resolution' => [
    'per_resource' => [
        'settings' => 'client_wins',    // Paramètres perso
        'preferences' => 'client_wins', // Préférences UI
        'drafts' => 'client_wins',      // Brouillons
    ],
],
```

**Résultat du conflit :**
```
Avant sync : Mobile = "Acheter 🍎" / Serveur = "Acheter 🍞"
Après sync : Mobile = "Acheter 🍎" / Serveur = "Acheter 🍎"
✅ Le client a gagné
```

---

### 3. Last Write Wins (Le dernier gagne)

**Comportement :** La version avec le **timestamp le plus récent** gagne.

**Avantages :**
- ✅ Logique et équitable
- ✅ Basé sur des faits (timestamp)
- ✅ Bon compromis général

**Inconvénients :**
- ⚠️ Dépend de l'horloge système
- ❌ Si les horloges sont désynchronisées, résultats imprévisibles

**Quand l'utiliser :**
- **Défaut recommandé** pour la plupart des cas
- Données collaboratives
- Documents partagés

**Exemple :**

```php
'conflict_resolution' => [
    'default_strategy' => 'last_write_wins', // Défaut
    'per_resource' => [
        'tasks' => 'last_write_wins',
        'projects' => 'last_write_wins',
    ],
],
```

**Résultat du conflit :**
```
Avant sync : 
  Mobile = "Acheter 🍎" (updated: 10:30)
  Serveur = "Acheter 🍞" (updated: 10:25)

Après sync :
  Mobile = "Acheter 🍎" / Serveur = "Acheter 🍎"
✅ Le mobile a gagné (plus récent)
```

**Cas inverse :**
```
Avant sync : 
  Mobile = "Acheter 🍎" (updated: 10:20)
  Serveur = "Acheter 🍞" (updated: 10:25)

Après sync :
  Mobile = "Acheter 🍞" / Serveur = "Acheter 🍞"
✅ Le serveur a gagné (plus récent)
```

---

### 4. Merge (Fusion intelligente)

**Comportement :** Fusionne les champs des deux versions en préférant les **valeurs non-null**.

**Avantages :**
- ✅ Aucune donnée perdue
- ✅ Combine le meilleur des deux
- ✅ Idéal pour les objets avec beaucoup de champs

**Inconvénients :**
- ⚠️ Peut créer des incohérences logiques
- ❌ Plus complexe à comprendre

**Quand l'utiliser :**
- Fiches produits avec nombreux champs
- Profils utilisateurs complets
- Documents structurés

**Exemple :**

```php
'conflict_resolution' => [
    'per_resource' => [
        'products' => 'merge',  // Fiches produits
        'profiles' => 'merge',  // Profils utilisateurs
    ],
],
```

**Résultat du conflit :**
```
Avant sync :
Mobile = {
  title: "Acheter 🍎",
  description: "Pommes rouges",
  quantity: null,
  priority: "high"
}

Serveur = {
  title: "Acheter 🍞",
  description: null,
  quantity: 3,
  priority: "medium"
}

Après sync (fusion) :
{
  title: "Acheter 🍞",        // Serveur (base)
  description: "Pommes rouges", // Mobile (non-null)
  quantity: 3,                  // Serveur (non-null)
  priority: "high"              // Mobile (non-null override)
}
✅ Fusion des deux versions
```

---

## ⚙️ Configuration

### Configuration globale

**config/offline-sync.php :**

```php
return [
    'conflict_resolution' => [
        // Stratégie par défaut pour toutes les ressources
        'default_strategy' => 'last_write_wins',
        
        // Stratégies spécifiques par ressource
        'per_resource' => [
            // Données critiques → Server Wins
            'users' => 'server_wins',
            'prices' => 'server_wins',
            'inventory' => 'server_wins',
            
            // Préférences personnelles → Client Wins
            'settings' => 'client_wins',
            'preferences' => 'client_wins',
            
            // Collaboratif → Last Write Wins
            'tasks' => 'last_write_wins',
            'projects' => 'last_write_wins',
            
            // Données complexes → Merge
            'products' => 'merge',
            'profiles' => 'merge',
        ],
    ],
];
```

### Changement dynamique

```php
use VendorName\OfflineSync\Facades\OfflineSync;

// Changer la stratégie à la volée
config(['offline-sync.conflict_resolution.per_resource.tasks' => 'client_wins']);

// Synchroniser
OfflineSync::sync(['tasks']);
```

---

## 💼 Cas d'Usage

### Cas 1 : Application de prise de notes

**Besoin :** L'utilisateur ne veut jamais perdre ses modifications locales.

**Solution :**

```php
'per_resource' => [
    'notes' => 'client_wins',
    'drafts' => 'client_wins',
],
```

### Cas 2 : Application de gestion d'inventaire

**Besoin :** L'inventaire du serveur est la source de vérité absolue.

**Solution :**

```php
'per_resource' => [
    'inventory' => 'server_wins',
    'stock_levels' => 'server_wins',
],
```

### Cas 3 : Application collaborative (Trello-like)

**Besoin :** Plusieurs utilisateurs modifient les mêmes tâches.

**Solution :**

```php
'per_resource' => [
    'tasks' => 'last_write_wins',
    'boards' => 'last_write_wins',
    'comments' => 'client_wins', // Les commentaires ne se surchargent pas
],
```

### Cas 4 : E-commerce avec fiches produits

**Besoin :** Combiner prix (serveur) et notes perso (local).

**Solution :**

```php
'per_resource' => [
    'products' => 'merge',
],
```

**Exemple :**
```
Mobile modifie :
{ id: 1, personal_note: "À acheter" }

Serveur modifie :
{ id: 1, price: 29.99 }

Résultat fusion :
{ id: 1, price: 29.99, personal_note: "À acheter" }
```

---

## 🔧 Stratégies Personnalisées

Vous pouvez créer vos propres stratégies de résolution.

### 1. Créer une stratégie

```php
<?php

namespace App\Sync\Strategies;

use VendorName\OfflineSync\Contracts\SyncStrategy;

class PriorityStrategy implements SyncStrategy
{
    public function resolve(array $conflict): array
    {
        $local = $conflict['local_data'];
        $remote = $conflict['remote_data'];
        
        // Gagner selon la priorité
        if (($local['priority'] ?? 0) > ($remote['priority'] ?? 0)) {
            return [
                'data' => $local,
                'winner' => 'client',
                'action' => 'force_push',
                'reason' => 'higher_priority',
            ];
        }
        
        return [
            'data' => $remote,
            'winner' => 'server',
            'action' => 'overwrite_local',
            'reason' => 'lower_priority',
        ];
    }

    public function name(): string
    {
        return 'priority';
    }
}
```

### 2. Enregistrer la stratégie

**app/Providers/AppServiceProvider.php :**

```php
use VendorName\OfflineSync\ConflictResolver;

public function boot()
{
    $this->app->bind('sync.strategy.priority', function () {
        return new \App\Sync\Strategies\PriorityStrategy();
    });
}
```

### 3. Utiliser la stratégie

```php
// Modifier le ConflictResolver pour supporter les stratégies custom
// Dans config
'per_resource' => [
    'tasks' => 'priority', // Votre stratégie custom
],
```

---

## 🐛 Debugging

### Écouter les événements de conflit

```php
use VendorName\OfflineSync\Events\ConflictDetected;

Event::listen(ConflictDetected::class, function ($event) {
    Log::warning('Conflit détecté', [
        'resource' => $event->conflict['resource'],
        'resource_id' => $event->conflict['resource_id'],
        'local' => $event->conflict['local_data'],
        'remote' => $event->conflict['remote_data'],
    ]);
});
```

### Logger les résolutions

```php
// Dans ConflictResolver
$result = $strategy->resolve($conflict);

Log::info('Conflit résolu', [
    'strategy' => $strategy->name(),
    'winner' => $result['winner'],
    'resource' => $conflict['resource'],
]);
```

### Afficher dans l'UI

```php
// Récupérer les conflits après sync
$result = OfflineSync::sync(['tasks']);

if (!empty($result['conflicts'])) {
    foreach ($result['conflicts'] as $conflict) {
        // Afficher un message à l'utilisateur
        session()->flash('warning', "Conflit sur {$conflict['resource']}");
    }
}
```

---

## 📊 Tableau Comparatif

| Stratégie | Préserve Local | Préserve Serveur | Complexité | Cas d'Usage |
|-----------|----------------|------------------|------------|-------------|
| **server_wins** | ❌ | ✅ | ⭐ Simple | Données critiques |
| **client_wins** | ✅ | ❌ | ⭐ Simple | Préférences perso |
| **last_write_wins** | ⚖️ Selon timestamp | ⚖️ Selon timestamp | ⭐⭐ Moyen | Collaboratif |
| **merge** | ✅ Partiel | ✅ Partiel | ⭐⭐⭐ Complexe | Fiches complètes |

---

## 🎯 Recommandations

### Par type d'application

| Type d'App | Recommandation |
|------------|----------------|
| **Notes personnelles** | `client_wins` partout |
| **E-commerce** | `server_wins` (prix, stock), `merge` (produits) |
| **CRM** | `last_write_wins` (contacts), `server_wins` (quotas) |
| **Gestion de tâches** | `last_write_wins` par défaut |
| **Inventaire** | `server_wins` partout |

### Best Practices

1. ✅ **Utiliser `last_write_wins` par défaut** - Bon compromis
2. ✅ **`server_wins` pour les données critiques** - Finances, stock
3. ✅ **`client_wins` pour les préférences** - UI, paramètres
4. ✅ **`merge` avec précaution** - Vérifier la logique métier
5. ✅ **Logger tous les conflits** - Pour analyse et debug
6. ✅ **Tester avec des timestamps proches** - Cas limite important

---

## ❓ FAQ

**Q : Que se passe-t-il si les timestamps sont égaux ?**
R : `last_write_wins` favorise le serveur par défaut.

**Q : Peut-on combiner plusieurs stratégies ?**
R : Oui, via une stratégie custom qui délègue selon les champs.

**Q : Comment éviter les conflits ?**
R : Sync fréquente, UX optimiste, verrouillage optimiste.

**Q : Les conflits bloquent-ils la sync ?**
R : Non, les autres items continuent. Les conflits sont reportés.

---

## 📞 Support

Questions sur les conflits ?
- 📧 Email : support@vendorname.com
- 📖 Documentation : https://docs.vendorname.com/offline-sync

---

**Maîtrisez les conflits !** 🎯
