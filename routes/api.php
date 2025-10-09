<?php

use App\Http\Controllers\Api\{AuthController, TransactionController};
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1'], function () {
    // Authentication Routes
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/whoami', [AuthController::class, 'whoami'])->middleware('auth:sanctum');
    });

    // Transactions Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'getTransactions']);
        Route::post('/transactions', [TransactionController::class, 'storeTransaction']);
    });

});
