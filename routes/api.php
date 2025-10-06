<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['prefix' => 'v1'], function () {
    // Authentication Routes
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/whoami', [AuthController::class, 'whoami'])->middleware('auth:sanctum');
    });

    // Transactions Routes
//    Route::middleware('auth:sanctum')->group(['prefix' => 'transactions'], function () {
//        Route::get('/', [TransactionController::class, 'getTransactions']);
//        Route::post('/', [TransactionController::class, 'storeTransaction']);
//    });

});
