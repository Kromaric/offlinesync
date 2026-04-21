<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limit index key length for MySQL < 5.7.7 / MariaDB < 10.2.2
        // which cap index size at 1000 bytes (utf8mb4 uses 4 bytes/char).
        Schema::defaultStringLength(191);

        // ── Inject auth header into every outgoing plugin sync request ──────────
        // The OfflineSync plugin is auth-agnostic: it reads
        // `offline-sync.security.headers` and adds them to each HTTP call.
        // We resolve the current request's Bearer token here so the plugin
        // automatically forwards it when pushing/pulling on behalf of this user.
        $request = $this->app->make(Request::class);
        $token   = $request->bearerToken();

        if ($token) {
            config(['offline-sync.security.headers' => [
                'Authorization' => 'Bearer ' . $token,
            ]]);
        }
    }
}
