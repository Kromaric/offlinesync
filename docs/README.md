# OfflineSync Documentation — Index

Complete index of all documentation for the NativePHP Offline Sync plugin.

---

## 📚 Main Documentation

### [README.md](../README.md)
**Plugin overview**
- Features and benefits
- Quick installation (5 steps)
- Quick Start with examples
- Basic usage (queue, sync, commands)
- Conflict resolution
- Backend setup
- Events
- Configuration
- Native support (Android / iOS)
- Tests
- License

---

## 📖 Detailed Guides

### 1. [INSTALL.md](INSTALL.md)
**Complete installation guide**

**Contents:**
- ✅ Requirements (PHP, Laravel, NativePHP, mobile)
- ✅ Step-by-step installation (12 steps)
- ✅ Basic configuration (.env)
- ✅ Model configuration (Syncable trait)
- ✅ Resource mapping
- ✅ Conflict configuration
- ✅ Backend setup (Sanctum, routes)
- ✅ Token injection via AppServiceProvider
- ✅ Mobile configuration (Android / iOS)
- ✅ Installation verification (4 tests)
- ✅ Initial troubleshooting
- ✅ Next steps

**For:** Developers installing the plugin for the first time

---

### 2. [BACKEND.md](BACKEND.md)
**Laravel API configuration**

**Contents:**
- ✅ Backend architecture
- ✅ Initial setup (Sanctum, CORS)
- ✅ Complete sync controller
- ✅ Authentication (tokens, revocation)
- ✅ Request validation
- ✅ Performance (cache, indexing, eager loading)
- ✅ Security (rate limiting, permissions)
- ✅ Monitoring (logs, metrics, alerts)
- ✅ Controller tests
- ✅ Custom configuration
- ✅ Best practices

**For:** Backend developers configuring the Laravel API

---

### 3. [CONFLICTS.md](CONFLICTS.md)
**Conflict resolution guide**

**Contents:**
- ✅ What is a conflict? (visual examples)
- ✅ The 4 strategies in detail:
  - Server Wins (advantages, disadvantages, use cases)
  - Client Wins (advantages, disadvantages, use cases)
  - Last Write Wins (advantages, disadvantages, use cases)
  - Merge (advantages, disadvantages, use cases)
- ✅ Global and per-resource configuration
- ✅ Dynamic changes
- ✅ 4 real-world use cases
- ✅ Custom strategies (creation, registration)
- ✅ Debugging (events, logs, UI)
- ✅ Comparison table
- ✅ Recommendations by app type
- ✅ Best practices
- ✅ FAQ (4 questions)

**For:** All developers using the plugin

---

### 4. [SECURITY.md](SECURITY.md)
**Security best practices**

**Contents:**
- ✅ Authentication (auth-agnostic design, AppServiceProvider pattern)
- ✅ HTTPS enforcement (Nginx / Apache server config)
- ✅ SSL certificates (Let's Encrypt)
- ✅ Data protection (field exclusion, sanitization)
- ✅ Strict validation
- ✅ Rate limiting (Laravel configuration)
- ✅ Audit & Monitoring (logs, alerts, metrics)
- ✅ Anomaly detection
- ✅ Production checklist (13 points)
- ✅ Server configuration (UFW, Fail2ban)
- ✅ Incident response
- ✅ Best practices (Top 10)
- ✅ Vulnerability reporting

**For:** Developers deploying to production

---

### 5. [TESTING.md](TESTING.md)
**Tests documentation**

**Contents:**
- ✅ Test structure (8 files, 45 tests)
- ✅ Run commands (test, test-unit, test-feature, coverage)
- ✅ Detailed unit tests:
  - QueueManagerTest (2 tests)
  - ConflictResolverTest (5 tests)
  - StrategiesTest (10 tests)
  - SyncEngineTest (10 tests)
- ✅ Detailed integration tests:
  - SyncFlowTest (9 tests)
  - OfflineOperationsTest (11 tests)
  - ConflictResolutionTest (9 tests)
- ✅ Coverage (targets per component)
- ✅ Writing new tests (structure, best practices, examples)
- ✅ Debugging tests
- ✅ Statistics
- ✅ CI/CD (GitHub Actions example)
- ✅ Next tests to add

**For:** Developers testing the plugin or contributing

---

### 6. [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
**FAQ and debugging**

**Contents:**
- ✅ Installation issues (3 common problems)
- ✅ Sync issues (4 problems + causes)
- ✅ Connectivity issues (2 problems)
- ✅ Conflict issues (2 problems)
- ✅ Performance issues (2 problems)
- ✅ Advanced debugging (logs, profiling, Telescope)
- ✅ FAQ (10 common questions)
- ✅ Diagnostic tools (complete script)
- ✅ Getting help (information to provide, channels)

**For:** Developers facing issues

---

## 📱 Native Documentation

### 7. [Android README.md](../native/android/README.md)
**Android (Kotlin) code documentation**

**Contents:**
- ✅ Android code structure
- ✅ Description of 3 components:
  - OfflineSyncFunctions.kt (7 methods)
  - ConnectivityMonitor.kt
  - BackgroundSyncWorker.kt
- ✅ Dependencies (WorkManager, OkHttp)
- ✅ Required permissions
- ✅ Usage from PHP / NativePHP
- ✅ Periodic sync configuration
- ✅ Supported versions (API 24+)
- ✅ WorkManager
- ✅ Sync flow
- ✅ Tests
- ✅ Debugging

**For:** Android developers or anyone debugging the Android layer

---

### 8. [iOS README.md](../native/ios/README.md)
**iOS (Swift) code documentation**

**Contents:**
- ✅ iOS code structure
- ✅ Description of 3 components:
  - OfflineSyncFunctions.swift (7 methods)
  - ConnectivityMonitor.swift
  - BackgroundSyncScheduler.swift
- ✅ Configuration (Package.swift, Info.plist, Capabilities)
- ✅ AppDelegate setup
- ✅ Usage in ViewController
- ✅ 10 complete examples
- ✅ iOS security (Network.framework, BackgroundTasks)
- ✅ Supported versions (iOS 14.0+)
- ✅ Debugging
- ✅ Performance
- ✅ Bridge notes

**For:** iOS developers or anyone debugging the iOS layer

---

## 📊 Documentation Statistics

| Document | Sections | For |
|----------|----------|-----|
| README | 15 | Everyone |
| INSTALL | 12 | Installation |
| BACKEND | 9 | Backend |
| CONFLICTS | 7 | Everyone |
| SECURITY | 8 | Production |
| TESTING | 7 | Tests |
| TROUBLESHOOTING | 7 | Debug |
| Android README | 11 | Android |
| iOS README | 12 | iOS |

---

## 🎯 By Role

### Full-Stack Developer
**Read first:**
1. README.md (overview)
2. INSTALL.md (setup)
3. BACKEND.md (API)
4. CONFLICTS.md (strategies)

### Frontend / Mobile Developer
**Read first:**
1. README.md (overview)
2. INSTALL.md (setup)
3. Android or iOS README (native code)
4. TROUBLESHOOTING.md (debug)

### DevOps / SysAdmin
**Read first:**
1. INSTALL.md (installation)
2. SECURITY.md (production)
3. BACKEND.md (server configuration)
4. TROUBLESHOOTING.md (debug)

### QA / Tester
**Read first:**
1. README.md (features)
2. TESTING.md (tests)
3. TROUBLESHOOTING.md (known issues)

---

## 🔍 By Use Case

### "I want to install the plugin"
→ [INSTALL.md](INSTALL.md)

### "I am configuring the backend API"
→ [BACKEND.md](BACKEND.md)

### "I have too many conflicts"
→ [CONFLICTS.md](CONFLICTS.md)

### "I am preparing for production"
→ [SECURITY.md](SECURITY.md)

### "I want to test the code"
→ [TESTING.md](TESTING.md)

### "I have a problem"
→ [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

### "I am developing on Android"
→ [Android README](../native/android/README.md)

### "I am developing on iOS"
→ [iOS README](../native/ios/README.md)

---

## 📝 Additional Documentation

### Changelog
**File:** [CHANGELOG.md](../dev/CHANGELOG.md)
**Contents:** Version history, added features, bug fixes

### License
**File:** [LICENSE](../LICENSE)
**Contents:** MIT licence, terms of use

### Tests
**File:** [tests/](../tests/)
**Contents:** Unit and integration tests with examples

---

## 🌐 External Resources

- **Official site**: https://offlinesync.techparse.fr
- **Online documentation**: https://docs.offlinesync.techparse.fr
- **GitHub repository**: https://github.com/Kromaric/offlinesync
- **Support**: offlinessync@techparse.fr

---

## 🤝 Contributing to Documentation

The documentation is open-source in the repository.

To propose improvements:
1. Fork the repository
2. Edit the `.md` files
3. Submit a Pull Request

Or send your suggestions to: offlinessync@techparse.fr

---

## 📞 Documentation Support

Questions about the documentation?
- 📧 Email: offlinessync@techparse.fr
- 🐛 Issues: https://github.com/Kromaric/offlinesync/issues

---

**Documentation complete and up to date!** 📚
