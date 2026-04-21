# Security Guide

Security best practices for OfflineSync in production.

---

## 🔒 Table of contents

1. [Authentication](#authentication)
2. [HTTPS](#https)
3. [Data protection](#data-protection)
4. [Rate limiting](#rate-limiting)
5. [Validation](#validation)
6. [Audit & Monitoring](#audit--monitoring)
7. [Production checklist](#production-checklist)
8. [Incident response](#incident-response)

---

## 🔐 Authentication

The plugin is **auth-agnostic**: it does not manage tokens or credentials. Your application is responsible for authentication. The plugin simply forwards whatever headers you inject via `offline-sync.security.headers`.

### Recommended pattern (AppServiceProvider)

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Http\Request;

public function boot(): void
{
    $token = $this->app->make(Request::class)->bearerToken();

    if ($token) {
        config(['offline-sync.security.headers' => [
            'Authorization' => 'Bearer ' . $token,
        ]]);
    }
}
```

This pattern works with any auth system: **Laravel Sanctum**, **Passport**, **API keys**, etc.

### Token generation (Laravel Sanctum)

```php
// In your AuthController (login)
$token = $user->createToken('mobile-app', ['sync'])->plainTextToken;

return response()->json(['token' => $token]);
```

### Token revocation

```php
// Revoke all tokens for a user
$user->tokens()->delete();

// Revoke a specific token
$user->tokens()->where('name', 'mobile-app')->delete();
```

---

## 🔒 HTTPS

### HTTPS enforcement

**Mandatory in production:**

```env
SYNC_REQUIRE_HTTPS=true
```

**Plugin-side check:**

```php
// The plugin rejects non-HTTPS connections
if (!str_starts_with($url, 'https://')) {
    throw new \Exception('HTTPS is required for sync operations');
}
```

### Server configuration

#### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name api.your-app.com;

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
    ServerName api.your-app.com

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

### SSL certificates

**Let's Encrypt (free):**

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain a certificate
sudo certbot --nginx -d api.your-app.com

# Auto-renewal
sudo certbot renew --dry-run
```

---

## 🛡️ Data Protection

### Sensitive data

**NEVER sync:**
- ❌ Plain-text passwords
- ❌ Credit card numbers
- ❌ Unencrypted medical data
- ❌ Tokens from other services

**Use field exclusion:**

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

**Backend side:**

```php
use Illuminate\Support\Str;

protected function applyChange(array $item, $user): array
{
    // Sanitize inputs
    $item['data']['title'] = strip_tags($item['data']['title']);
    $item['data']['description'] = Str::limit(
        strip_tags($item['data']['description']),
        1000
    );

    // ...
}
```

### Strict validation

```php
$validated = $request->validate([
    'items.*.data.title' => 'required|string|max:255',
    'items.*.data.email' => 'nullable|email',
    'items.*.data.age'   => 'nullable|integer|min:0|max:150',
]);
```

---

## ⏱️ Rate Limiting

### Laravel configuration

**app/Providers/RouteServiceProvider.php:**

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

protected function configureRateLimiting()
{
    // Global IP limit
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->ip());
    });

    // Sync-specific limit
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

**Routes:**

```php
Route::middleware(['auth:sanctum', 'throttle:sync'])->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push']);
    Route::get('/pull/{resource}', [SyncController::class, 'pull']);
});
```

### Batch size limit

```php
public function push(Request $request)
{
    $validated = $request->validate([
        'items' => 'required|array|max:100', // Max 100 items per request
    ]);

    // ...
}
```

---

## ✅ Validation

### Timestamp validation

```php
$validated = $request->validate([
    'items.*.timestamp' => [
        'required',
        'date',
        'before_or_equal:now',
        'after:' . now()->subYears(1)->toDateString(), // Not too old
    ],
]);
```

### UUID format validation

```php
$validated = $request->validate([
    'items.*.resource_id' => 'nullable|uuid',
]);
```

### Per-resource validation

```php
protected function validateResourceData(string $resource, array $data): array
{
    $rules = match($resource) {
        'tasks' => [
            'title'     => 'required|string|max:255',
            'completed' => 'boolean',
        ],
        'users' => [
            'name'  => 'required|string|max:100',
            'email' => 'required|email',
        ],
        default => [],
    };

    return Validator::make($data, $rules)->validate();
}
```

---

## 📊 Audit & Monitoring

### Security logs

```php
use Illuminate\Support\Facades\Log;

// Log all sync attempts
Log::channel('security')->info('Sync attempt', [
    'user_id'     => $user->id,
    'ip'          => $request->ip(),
    'user_agent'  => $request->userAgent(),
    'items_count' => count($items),
]);

// Log failures
Log::channel('security')->warning('Sync failed', [
    'user_id' => $user->id,
    'reason'  => $e->getMessage(),
    'ip'      => $request->ip(),
]);

// Log unauthorized access
Log::channel('security')->error('Unauthorized sync attempt', [
    'ip'                 => $request->ip(),
    'attempted_resource' => $resource,
]);
```

### Automated alerts

```php
use Illuminate\Support\Facades\Mail;

if ($failedAttempts > 10) {
    Mail::to('security@example.com')->send(
        new SuspiciousActivityAlert($user, $request)
    );
}
```

### Security metrics

```php
use Illuminate\Support\Facades\Redis;

// Count attempts per IP
$attempts = Redis::incr("sync:attempts:{$ip}");
Redis::expire("sync:attempts:{$ip}", 3600); // 1 hour

if ($attempts > 100) {
    // Temporarily block
    Cache::put("blocked:ip:{$ip}", true, now()->addHours(24));
}
```

---

## 🔍 Anomaly Detection

### Unusual volume

```php
$avgItemsPerSync = DB::table('offline_sync_logs')
    ->where('user_id', $user->id)
    ->avg('items_count');

if (count($items) > $avgItemsPerSync * 5) {
    Log::warning('Unusually large sync', [
        'user_id' => $user->id,
        'items'   => count($items),
        'average' => $avgItemsPerSync,
    ]);
}
```

### Unusual hours

```php
$hour = now()->hour;

if ($hour >= 2 && $hour <= 5) {
    Log::info('Late night sync', [
        'user_id' => $user->id,
        'hour'    => $hour,
    ]);
}
```

---

## 📋 Production Checklist

### Before deployment

- [ ] ✅ HTTPS enabled with a valid certificate
- [ ] ✅ `SYNC_REQUIRE_HTTPS=true` in production
- [ ] ✅ `APP_KEY` generated and secured
- [ ] ✅ Sanctum tokens with expiration
- [ ] ✅ Rate limiting configured
- [ ] ✅ Strict input validation in place
- [ ] ✅ Security logging enabled
- [ ] ✅ Sensitive fields excluded from sync
- [ ] ✅ CORS configured correctly
- [ ] ✅ Firewall configured (UFW / iptables)
- [ ] ✅ Fail2ban installed (optional)
- [ ] ✅ Database backups in place
- [ ] ✅ Sanctum token rotation plan

### Server configuration

**UFW (Ubuntu):**

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP (redirect)
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

**Fail2ban:**

```ini
# /etc/fail2ban/jail.local
[laravel-sync]
enabled  = true
port     = http,https
filter   = laravel-sync
logpath  = /var/www/storage/logs/laravel.log
maxretry = 5
bantime  = 3600
```

---

## 🚨 Incident Response

### In case of compromise

1. **Immediately revoke all tokens**

```php
DB::table('personal_access_tokens')->truncate();
```

2. **Rotate APP_KEY**

```bash
php artisan key:generate
```

3. **Analyse logs**

```bash
grep "Sync failed" storage/logs/laravel.log | tail -100
```

4. **Notify affected users**

5. **Full security audit**

---

## 🎯 Top 10 Best Practices

1. ✅ **HTTPS only** in production
2. ✅ **Auth injected** via `security.headers` in AppServiceProvider
3. ✅ **Sanctum tokens** with revocation
4. ✅ **Aggressive rate limiting**
5. ✅ **Strict validation** of all inputs
6. ✅ **Complete security logs**
7. ✅ **Exclude sensitive fields** from sync
8. ✅ **Valid and renewed SSL certificates**
9. ✅ **Firewall** configured
10. ✅ **Active monitoring**

---

## 📞 Vulnerability Reporting

If you discover a security vulnerability:

**Do NOT** open a public GitHub issue.

**Email:** offlinessync@techparse.fr

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (optional)

We commit to responding within 48 hours.

---

## 📚 Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [Sanctum Documentation](https://laravel.com/docs/sanctum)

---

**Keep your sync secure!** 🔒
