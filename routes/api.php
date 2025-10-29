<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Dashboard routes
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
        Route::get('/user-growth', [DashboardController::class, 'userGrowth']);
        Route::get('/action-stats', [DashboardController::class, 'actionStats']);
        Route::get('/top-active-users', [DashboardController::class, 'topActiveUsers']);
    });

    // User management routes
    Route::apiResource('users', UserController::class);

    // Admin management routes
    Route::apiResource('admins', AdminController::class);

    // Role management routes
    Route::apiResource('roles', RoleController::class);

    // Permission management routes
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::delete('/{id}', [PermissionController::class, 'destroy']);

        // Assign permissions to role
        Route::post('/roles/{roleId}/assign', [PermissionController::class, 'assignToRole']);
        Route::get('/roles/{roleId}', [PermissionController::class, 'getRolePermissions']);

        // Assign permissions to user
        Route::post('/users/{userId}/assign', [PermissionController::class, 'assignToUser']);
        Route::get('/users/{userId}', [PermissionController::class, 'getUserPermissions']);
    });

    // Audit logs routes
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/{id}', [AuditLogController::class, 'show']);
        Route::get('/user/{userId}', [AuditLogController::class, 'userLogs']);
        Route::get('/model/{modelType}/{modelId}', [AuditLogController::class, 'modelLogs']);
    });
});
