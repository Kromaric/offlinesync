# Documentation OfflineSync - Index

Index complet de toute la documentation du plugin NativePHP Offline Sync & Backup.

---

## 📚 Documentation Principale

### [README.md](../README.md)
**Vue d'ensemble du plugin**
- Features et avantages
- Installation rapide (5 étapes)
- Quick Start avec exemples
- Usage de base (queue, sync, commands)
- Résolution de conflits
- Backend setup
- Events
- Configuration
- Support natif (Android/iOS)
- Tests
- License et pricing

---

## 📖 Guides Détaillés

### 1. [INSTALL.md](INSTALL.md) - 9.16 KB
**Guide d'installation complet**

**Contenu :**
- ✅ Prérequis (PHP, Laravel, NativePHP, mobile)
- ✅ Installation étape par étape (11 étapes)
- ✅ Configuration de base (.env)
- ✅ Configuration des modèles (trait Syncable)
- ✅ Mapping des ressources
- ✅ Configuration des conflits
- ✅ Setup backend (Sanctum, routes)
- ✅ Configuration mobile (Android/iOS)
- ✅ Vérification de l'installation (4 tests)
- ✅ Dépannage initial
- ✅ Prochaines étapes

**Pour qui :** Développeurs qui installent le plugin pour la première fois

---

### 2. [BACKEND.md](BACKEND.md) - 17.40 KB
**Configuration API Laravel**

**Contenu :**
- ✅ Architecture du backend
- ✅ Setup initial (Sanctum, CORS)
- ✅ Controller de synchronisation complet
- ✅ Authentification (tokens, révocation)
- ✅ Validation des requêtes
- ✅ Performance (cache, indexation, eager loading)
- ✅ Sécurité (rate limiting, permissions)
- ✅ Monitoring (logs, métriques, alertes)
- ✅ Tests du controller
- ✅ Configuration personnalisée
- ✅ Best practices (10 points)

**Pour qui :** Développeurs backend qui configurent l'API Laravel

---

### 3. [CONFLICTS.md](CONFLICTS.md) - 13.80 KB
**Guide résolution de conflits**

**Contenu :**
- ✅ Qu'est-ce qu'un conflit ? (exemples visuels)
- ✅ Les 4 stratégies détaillées :
  - Server Wins (avantages, inconvénients, cas d'usage)
  - Client Wins (avantages, inconvénients, cas d'usage)
  - Last Write Wins (avantages, inconvénients, cas d'usage)
  - Merge (avantages, inconvénients, cas d'usage)
- ✅ Configuration globale et per-resource
- ✅ Changement dynamique
- ✅ 4 cas d'usage réels
- ✅ Stratégies personnalisées (création, enregistrement)
- ✅ Debugging (events, logs, UI)
- ✅ Tableau comparatif
- ✅ Recommandations par type d'app
- ✅ Best practices
- ✅ FAQ (4 questions)

**Pour qui :** Tous les développeurs utilisant le plugin

---

### 4. [SECURITY.md](SECURITY.md) - 11.32 KB
**Best practices sécurité**

**Contenu :**
- ✅ Authentification (Sanctum vs API Key)
- ✅ Chiffrement (queue locale, tokens)
- ✅ HTTPS enforcement (configuration serveur Nginx/Apache)
- ✅ Certificats SSL (Let's Encrypt)
- ✅ Protection des données (exclusion, sanitization)
- ✅ Validation stricte
- ✅ Rate limiting (configuration Laravel)
- ✅ Audit & Monitoring (logs, alertes, métriques)
- ✅ Détection d'anomalies
- ✅ Checklist production (14 points)
- ✅ Configuration serveur (UFW, Fail2ban)
- ✅ Incident response
- ✅ Best practices (Top 10)
- ✅ Signalement de vulnérabilité

**Pour qui :** Développeurs déployant en production

---

### 5. [TESTING.md](TESTING.md) - 9.06 KB
**Documentation des tests**

**Contenu :**
- ✅ Structure des tests (8 fichiers, 45 tests)
- ✅ Commandes d'exécution (test, test-unit, test-feature, coverage)
- ✅ Tests unitaires détaillés :
  - QueueManagerTest (2 tests)
  - ConflictResolverTest (5 tests)
  - StrategiesTest (10 tests)
  - SyncEngineTest (10 tests)
- ✅ Tests d'intégration détaillés :
  - SyncFlowTest (9 tests)
  - OfflineOperationsTest (11 tests)
  - ConflictResolutionTest (9 tests)
- ✅ Coverage (cibles par composant)
- ✅ Écrire de nouveaux tests (structure, best practices, exemples)
- ✅ Debugging tests
- ✅ Statistiques
- ✅ CI/CD (exemple GitHub Actions)
- ✅ Prochains tests à ajouter

**Pour qui :** Développeurs qui testent le plugin ou contribuent

---

### 6. [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - 12.44 KB
**FAQ et debugging**

**Contenu :**
- ✅ Problèmes d'installation (3 problèmes fréquents)
- ✅ Problèmes de synchronisation (4 problèmes + causes)
- ✅ Problèmes de connectivité (2 problèmes)
- ✅ Problèmes de conflits (2 problèmes)
- ✅ Problèmes de performance (2 problèmes)
- ✅ Debugging avancé (logs, profiling, Telescope)
- ✅ FAQ (10 questions courantes)
- ✅ Outils de diagnostic (script complet)
- ✅ Obtenir de l'aide (informations à fournir, canaux)

**Pour qui :** Développeurs rencontrant des problèmes

---

## 📱 Documentation Native

### 7. [Android README.md](../native/android/README.md) - 4.42 KB
**Documentation code Android (Kotlin)**

**Contenu :**
- ✅ Structure du code Android
- ✅ Description des 3 composants :
  - OfflineSyncFunctions.kt (7 méthodes)
  - ConnectivityMonitor.kt
  - BackgroundSyncWorker.kt
- ✅ Dépendances (WorkManager, OkHttp)
- ✅ Permissions requises
- ✅ Utilisation depuis PHP/NativePHP
- ✅ Configuration sync périodique
- ✅ Versions supportées (API 24+)
- ✅ WorkManager
- ✅ Flux de synchronisation
- ✅ Tests
- ✅ Debugging

**Pour qui :** Développeurs Android ou qui debuggent la partie Android

---

### 8. [iOS README.md](../native/ios/README.md) - 7+ KB (nouveau)
**Documentation code iOS (Swift)**

**Contenu :**
- ✅ Structure du code iOS
- ✅ Description des 3 composants :
  - OfflineSyncFunctions.swift (7 méthodes)
  - ConnectivityMonitor.swift
  - BackgroundSyncScheduler.swift
- ✅ Configuration (Package.swift, Info.plist, Capabilities)
- ✅ Setup dans AppDelegate
- ✅ Utilisation dans ViewController
- ✅ 10 exemples complets
- ✅ Sécurité iOS (Network.framework, BackgroundTasks)
- ✅ Versions supportées (iOS 14.0+)
- ✅ Debugging
- ✅ Performance
- ✅ Point d'attention (bridge)

**Pour qui :** Développeurs iOS ou qui debuggent la partie iOS

---

## 📊 Statistiques de Documentation

| Document | Taille | Sections | Pour qui |
|----------|--------|----------|----------|
| README | 7.68 KB | 15 | Tous |
| INSTALL | 9.16 KB | 11 | Installation |
| BACKEND | 17.40 KB | 9 | Backend |
| CONFLICTS | 13.80 KB | 7 | Tous |
| SECURITY | 11.32 KB | 8 | Production |
| TESTING | 9.06 KB | 7 | Tests |
| TROUBLESHOOTING | 12.44 KB | 7 | Debug |
| Android README | 4.42 KB | 11 | Android |
| iOS README | ~7 KB | 12 | iOS |

**Total documentation : ~92 KB** de documentation complète

---

## 🎯 Par Rôle

### Développeur Full-Stack
**Lire en priorité :**
1. README.md (overview)
2. INSTALL.md (setup)
3. BACKEND.md (API)
4. CONFLICTS.md (stratégies)

### Développeur Frontend/Mobile
**Lire en priorité :**
1. README.md (overview)
2. INSTALL.md (setup)
3. Android ou iOS README (code natif)
4. TROUBLESHOOTING.md (debug)

### DevOps/SysAdmin
**Lire en priorité :**
1. INSTALL.md (installation)
2. SECURITY.md (production)
3. BACKEND.md (configuration serveur)
4. TROUBLESHOOTING.md (debug)

### QA/Testeur
**Lire en priorité :**
1. README.md (features)
2. TESTING.md (tests)
3. TROUBLESHOOTING.md (bugs connus)

---

## 🔍 Par Cas d'Usage

### "Je veux installer le plugin"
→ [INSTALL.md](INSTALL.md)

### "Je configure l'API backend"
→ [BACKEND.md](BACKEND.md)

### "J'ai trop de conflits"
→ [CONFLICTS.md](CONFLICTS.md)

### "Je prépare la production"
→ [SECURITY.md](SECURITY.md)

### "Je veux tester le code"
→ [TESTING.md](TESTING.md)

### "J'ai un problème"
→ [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

### "Je développe sur Android"
→ [Android README](../native/android/README.md)

### "Je développe sur iOS"
→ [iOS README](../native/ios/README.md)

---

## 📝 Documentation Additionnelle

### Changelog
**Fichier :** [CHANGELOG.md](../dev/CHANGELOG.md)
**Contenu :** Historique des versions, features ajoutées, bugs corrigés

### License
**Fichier :** [LICENSE](../LICENSE)
**Contenu :** Licence propriétaire, termes d'utilisation

### Tests
**Fichier :** [tests/](../tests/)
**Contenu :** Tests unitaires et d'intégration avec exemples

---

## 🌐 Ressources Externes

- **Site officiel** : https://techparse.fr/offline-sync
- **Documentation en ligne** : https://docs.techparse.fr/offline-sync
- **Repository GitHub** : https://github.com/Kromaric/offline-sync
- **Support** : support@techparse.fr

---

## 🎓 Tutoriels Vidéo (à venir)

- [ ] Installation complète (15 min)
- [ ] Configuration backend (10 min)
- [ ] Gestion des conflits (8 min)
- [ ] Déploiement production (12 min)

---

## 🤝 Contribuer à la Documentation

La documentation est open-source dans le repository.

Pour proposer des améliorations :
1. Fork le repository
2. Éditez les fichiers .md
3. Soumettez une Pull Request

Ou envoyez vos suggestions à : docs@techparse.fr

---

## 📞 Support Documentation

Questions sur la documentation ?
- 📧 Email : docs@techparse.fr
- 💬 Discord : https://discord.gg/offlineSync

---

**Documentation complète et à jour !** 📚
