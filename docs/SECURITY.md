# Guide Sécurité

Best practices de sécurité pour OfflineSync en production.

---

## 🔒 Table des matières

1. [Authentification](#authentification)
2. [Chiffrement](#chiffrement)
3. [HTTPS](#https)
4. [Protection des données](#protection-des-données)
5. [Rate Limiting](#rate-limiting)
6. [Validation](#validation)
7. [Audit & Monitoring](#audit--monitoring)
8. [Checklist production](#checklist-production)

---

## 🔐 Authentification

### Laravel Sanctum (Recommandé)

**Avantages :**
- ✅ Intégré à Laravel
- ✅ Tokens révocables
- ✅ Simple à implémenter
- ✅ Support multi-devices

**Configuration :**

```env
SYNC_AUTH_METHOD=bearer
```

**Génération de token :**

```php
// Lors du login
$user = User::find(1);
$token = $user->createToken('mobile-app', ['sync'])->plainTextToken;

// Stocker le token dans l'app mobile de manière sécurisée
```

**Révocation :**

```php
// Révoquer tous les tokens de l'utilisateur
$user->tokens()->delete();

// Révoquer un token spécifique
$user->tokens()->where('name', 'mobile-app')->delete();
```

### API Key (Alternative)

Pour les cas simples sans authentification utilisateur :

```env
SYNC_AUTH_METHOD=api_key
SYNC_API_TOKEN=VOTRE_CLE_SECRETE_LONGUE_ET_ALEATOIRE
```

**⚠️ Important :**
- Utiliser un token long (min 32 caractères)
- Générer avec `openssl_random_pseudo_bytes()`
- Ne JAMAIS commiter dans Git
- Rotation régulière recommandée

---

## 🔐 Chiffrement

### Chiffrement de la queue locale

Activé par défaut pour protéger les données sensibles :

```env
SYNC_ENCRYPT_QUEUE=true
```

**Ce qui est chiffré :**
- ✅ Payload des items en queue
- ✅ Données utilisateur sensibles
- ✅ Tokens stockés localement

**Algorithme :** AES-256-CBC (via Laravel Crypt)

**Key management :**

```php
// La clé APP_KEY de Laravel est utilisée
APP_KEY=base64:VOTRE_CLE_APPLICATION_32_CARACTERES
```

**⚠️ Sécurité de APP_KEY :**
```bash
# Générer une nouvelle clé
php artisan key:generate

# Vérifier la clé
php artisan tinker
>>> config('app.key')
```

### Chiffrement du token utilisateur

```env
SYNC_TOKEN_STORAGE=encrypted
```

**Emplacement :** `storage/app/sync_token.enc`

**Code :**

```php
use Illuminate\Support\Facades\Crypt;

// Stockage
$encrypted = Crypt::encryptString($token);
file_put_contents(storage_path('app/sync_token.enc'), $encrypted);

// Récupération
$encrypted = file_get_contents(storage_path('app/sync_token.enc'));
$token = Crypt::decryptString($encrypted);
```

---

## 🔒 HTTPS

### Enforcement HTTPS

**OBLIGATOIRE en production :**

```env
SYNC_REQUIRE_HTTPS=true
```

**Vérification côté plugin :**

```php
// Le plugin refuse les connexions HTTP non sécurisées
if (!str_starts_with($url, 'https://')) {
    throw new \Exception('HTTPS is required for sync operations');
}
```

### Configuration serveur

#### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name api.votre-app.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### Apache

```apache
<VirtualHost *:443>
    ServerName api.votre-app.com
    
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
    
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5
    
    Header always set Strict-Transport-Security "max-age=31536000"
    
    ProxyPass / http://localhost:8000/
    ProxyPassReverse / http://localhost:8000/
</VirtualHost>
```

### Certificats SSL

**Let's Encrypt (Gratuit) :**

```bash
# Installation Certbot
sudo apt install certbot python3-certbot-nginx

# Obtenir un certificat
sudo certbot --nginx -d api.votre-app.com

# Auto-renouvellement
sudo certbot renew --dry-run
```

---

## 🛡️ Protection des Données

### Données sensibles

**Ne JAMAIS synchroniser :**
- ❌ Mots de passe en clair
- ❌ Numéros de carte bancaire
- ❌ Données médicales non chiffrées
- ❌ Tokens d'autres services

**Utiliser l'exclusion :**

```php
class User extends Model
{
    use Syncable;
    
    protected $syncExcluded = [
        'password',
        'remember_token',
        'credit_card',
        'ssn',
    ];
}
```

### Sanitization

**Côté Backend :**

```php
use Illuminate\Support\Str;

protected function applyChange(array $item, $user): array
{
    // Nettoyer les entrées
    $item['data']['title'] = strip_tags($item['data']['title']);
    $item['data']['description'] = Str::limit(
        strip_tags($item['data']['description']),
        1000
    );
    
    // ...
}
```

### Validation stricte

```php
$validated = $request->validate([
    'items.*.data.title' => 'required|string|max:255',
    'items.*.data.email' => 'nullable|email',
    'items.*.data.age' => 'nullable|integer|min:0|max:150',
]);
```

---

## ⏱️ Rate Limiting

### Configuration Laravel

**app/Providers/RouteServiceProvider.php :**

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

protected function configureRateLimiting()
{
    // Limite globale par IP
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->ip());
    });
    
    // Limite spécifique pour sync
    RateLimiter::for('sync', function (Request $request) {
        return Limit::perMinute(30)
            ->by($request->user()?->id ?: $request->ip())
            ->response(function (Request $request, array $headers) {
                return response()->json([
                    'error' => 'Too many sync requests. Please slow down.',
                ], 429, $headers);
            });
    });
}
```

**Routes :**

```php
Route::middleware(['auth:sanctum', 'throttle:sync'])->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push']);
    Route::get('/pull/{resource}', [SyncController::class, 'pull']);
});
```

### Limite par taille de batch

```php
public function push(Request $request)
{
    $validated = $request->validate([
        'items' => 'required|array|max:100', // Max 100 items
    ]);
    
    // ...
}
```

---

## ✅ Validation

### Validation des timestamps

```php
$validated = $request->validate([
    'items.*.timestamp' => [
        'required',
        'date',
        'before_or_equal:now',
        'after:' . now()->subYears(1)->toDateString(), // Pas trop ancien
    ],
]);
```

### Validation du format UUID

```php
$validated = $request->validate([
    'items.*.resource_id' => 'nullable|uuid',
]);
```

### Validation par ressource

```php
protected function validateResourceData(string $resource, array $data): array
{
    $rules = match($resource) {
        'tasks' => [
            'title' => 'required|string|max:255',
            'completed' => 'boolean',
        ],
        'users' => [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
        ],
        default => [],
    };
    
    return Validator::make($data, $rules)->validate();
}
```

---

## 📊 Audit & Monitoring

### Logs de sécurité

```php
use Illuminate\Support\Facades\Log;

// Logger toutes les syncs
Log::channel('security')->info('Sync attempt', [
    'user_id' => $user->id,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'items_count' => count($items),
]);

// Logger les échecs
Log::channel('security')->warning('Sync failed', [
    'user_id' => $user->id,
    'reason' => $e->getMessage(),
    'ip' => $request->ip(),
]);

// Logger les accès non autorisés
Log::channel('security')->error('Unauthorized sync attempt', [
    'ip' => $request->ip(),
    'attempted_resource' => $resource,
]);
```

### Alertes automatiques

```php
use Illuminate\Support\Facades\Mail;

if ($failedAttempts > 10) {
    Mail::to('security@example.com')->send(
        new SuspiciousActivityAlert($user, $request)
    );
}
```

### Métriques de sécurité

```php
use Illuminate\Support\Facades\Redis;

// Compter les tentatives par IP
$attempts = Redis::incr("sync:attempts:{$ip}");
Redis::expire("sync:attempts:{$ip}", 3600); // 1h

if ($attempts > 100) {
    // Bloquer temporairement
    Cache::put("blocked:ip:{$ip}", true, now()->addHours(24));
}
```

---

## 🔍 Détection d'anomalies

### Volume inhabituel

```php
$avgItemsPerSync = DB::table('offline_sync_logs')
    ->where('user_id', $user->id)
    ->avg('items_count');

if (count($items) > $avgItemsPerSync * 5) {
    Log::warning('Unusually large sync', [
        'user_id' => $user->id,
        'items' => count($items),
        'average' => $avgItemsPerSync,
    ]);
}
```

### Horaires inhabituels

```php
$hour = now()->hour;

if ($hour >= 2 && $hour <= 5) {
    Log::info('Late night sync', [
        'user_id' => $user->id,
        'hour' => $hour,
    ]);
}
```

---

## 📋 Checklist Production

### Avant le déploiement

- [ ] ✅ HTTPS activé et certificat valide
- [ ] ✅ `SYNC_REQUIRE_HTTPS=true` en production
- [ ] ✅ `SYNC_ENCRYPT_QUEUE=true`
- [ ] ✅ APP_KEY généré et sécurisé
- [ ] ✅ Tokens Sanctum avec expiration
- [ ] ✅ Rate limiting configuré
- [ ] ✅ Validation stricte en place
- [ ] ✅ Logs de sécurité activés
- [ ] ✅ Champs sensibles exclus de la sync
- [ ] ✅ CORS configuré correctement
- [ ] ✅ Firewall configuré (UFW/iptables)
- [ ] ✅ Fail2ban installé (optionnel)
- [ ] ✅ Backup de la base de données
- [ ] ✅ Plan de rotation des tokens

### Configuration serveur

**UFW (Ubuntu) :**

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP (redirect)
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

**Fail2ban :**

```ini
# /etc/fail2ban/jail.local
[laravel-sync]
enabled = true
port = http,https
filter = laravel-sync
logpath = /var/www/storage/logs/laravel.log
maxretry = 5
bantime = 3600
```

---

## 🚨 Incident Response

### En cas de compromission

1. **Révoquer immédiatement tous les tokens**
```php
DB::table('personal_access_tokens')->truncate();
```

2. **Changer APP_KEY**
```bash
php artisan key:generate
```

3. **Analyser les logs**
```bash
grep "Sync failed" storage/logs/laravel.log | tail -100
```

4. **Notifier les utilisateurs**

5. **Audit complet de sécurité**

---

## 🎯 Best Practices Résumées

### Top 10

1. ✅ **HTTPS uniquement** en production
2. ✅ **Chiffrement de la queue** activé
3. ✅ **Tokens Sanctum** avec révocation
4. ✅ **Rate limiting** agressif
5. ✅ **Validation stricte** de toutes les entrées
6. ✅ **Logs de sécurité** complets
7. ✅ **Exclusion des données** sensibles
8. ✅ **Certificats SSL** valides et renouvelés
9. ✅ **Firewall** configuré
10. ✅ **Monitoring** actif

---

## 📞 Signalement de vulnérabilité

Si vous découvrez une faille de sécurité :

**Ne PAS** créer d'issue publique GitHub.

**Envoyer un email à :** security@techparse.fr

Inclure :
- Description de la vulnérabilité
- Steps to reproduce
- Impact potentiel
- Suggestion de fix (optionnel)

Nous nous engageons à répondre sous 48h.

---

## 📚 Ressources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [Sanctum Documentation](https://laravel.com/docs/sanctum)

---

**Sécurisez votre sync !** 🔒
