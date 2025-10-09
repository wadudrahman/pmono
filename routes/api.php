<?php

use App\Http\Controllers\Api\{AuthController, TransactionController};
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['sanitize.input', 'security.headers']], function () {
    // Authentication Routes
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/whoami', [AuthController::class, 'whoami'])->middleware('auth:sanctum');
    });

    // Transactions Routes
    Route::middleware(['auth:sanctum', 'rate.limit:transaction'])->group(function () {
        Route::get('/transactions', [TransactionController::class, 'getTransactions']);
        Route::post('/transactions', [TransactionController::class, 'storeTransaction']);
        Route::get('/transactions/{id}', [TransactionController::class, 'getTransaction']);
        Route::get('/transaction-summary', [TransactionController::class, 'getSummary']);
    });

    // User Profile Routes
    Route::middleware(['auth:sanctum', 'rate.limit:read'])->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

});

// Health check endpoint (no auth required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});
