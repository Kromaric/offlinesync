<?php

namespace App\Providers;

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
    }
}
