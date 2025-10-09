<?php

use Illuminate\Support\Facades\Route;

// Test route
Route::get('/test', function () {
    return view('test');
});

// Catch all route for Vue SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
