<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware(['web', 'throttle:login']);
    Route::post('/token', [AuthController::class, 'tokenLogin'])->middleware('throttle:login');

    Route::middleware(['web', 'auth:sanctum'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/token/logout', [AuthController::class, 'tokenLogout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{order}', [OrderController::class, 'update']);
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);
    Route::post('/orders/{order}/ready-to-ship', [OrderController::class, 'readyToShip']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
});
