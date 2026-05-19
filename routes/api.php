<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionEntryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/dashboard', [DashboardController::class, 'index']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // User
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/update', [UserController::class, 'update']);

    // Products
    Route::apiResource('products', ProductController::class);

    // Production
    Route::apiResource('production-entries', ProductionEntryController::class);

    // Maintenance
    Route::prefix('maintenance')->group(function () {

        // ── Unit & plant endpoints (existing) ─────────────────────────────────────

        // All plants with units and sub-units (main dashboard feed)
        Route::get('/', [MaintenanceController::class, 'index']);

        // Status counts per plant
        Route::get('/summary', [MaintenanceController::class, 'summary']);

        // Single plant with its units
        Route::get('/plants/{plant}', [MaintenanceController::class, 'showPlant']);

        // Unit CRUD
        Route::post('/units', [MaintenanceController::class, 'storeUnit']);
        Route::patch('/units/{unit}', [MaintenanceController::class, 'updateUnit']);
        Route::delete('/units/{unit}', [MaintenanceController::class, 'destroyUnit']);

        // ── Log endpoints (new) ───────────────────────────────────────────────────

        // Submit a daily check for a unit → creates log + updates unit snapshot
        Route::post('/units/{unit}/check', [MaintenanceLogController::class, 'submitCheck']);

        // Full paginated log history for a unit
        Route::get('/units/{unit}/logs', [MaintenanceLogController::class, 'unitHistory']);

        // Status history over a date range — query params: ?from=2025-05-01&to=2025-05-16
        Route::get('/units/{unit}/logs/history', [MaintenanceLogController::class, 'unitStatusHistory']);

        // All checks submitted today, grouped by plant
        Route::get('/logs/today', [MaintenanceLogController::class, 'today']);

        // Units not yet checked today
        Route::get('/logs/unchecked', [MaintenanceLogController::class, 'unchecked']);

        // Daily completion % per plant
        Route::get('/logs/completion', [MaintenanceLogController::class, 'completion']);

        // All checks by a specific user
        Route::get('/logs/user/{user}', [MaintenanceLogController::class, 'userHistory']);

        Route::get('/logs/date/{date}', [MaintenanceLogController::class, 'byDate']);
    });

});
