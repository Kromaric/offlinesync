<?php

use Orchestra\Testbench\Foundation\Application;
use Orchestra\Testbench\Foundation\Bootstrap\SyncTestbenchCachedRoutes;

/**
 * Bootstrap the Orchestra Testbench application using this directory as the
 * Laravel skeleton so bootstrap/cache is committed to git and always present
 * (avoids the is_writable() false-negative on Windows for the vendor skeleton).
 */
$app = Application::create(
    basePath: realpath(__DIR__ . '/..'),
);

(new SyncTestbenchCachedRoutes)->bootstrap($app);

return $app;
