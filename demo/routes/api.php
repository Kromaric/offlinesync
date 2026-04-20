<?php

use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\AuthController;
use Techparse\OfflineSync\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/sync/ping', [SyncController::class, 'ping']);

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Tasks CRUD
    Route::apiResource('tasks', TaskController::class);

    // Actions supplémentaires sur tasks
    Route::post('/tasks/{id}/toggle', [TaskController::class, 'toggleComplete']);
    Route::get('/tasks-stats', [TaskController::class, 'stats']);

    // OfflineSync routes
    Route::prefix('sync')->group(function () {
        Route::post('/push', [SyncController::class, 'push']);
        Route::get('/pull/{resource}', [SyncController::class, 'pull']);
        Route::get('/status', [SyncController::class, 'status']);
    });
});
