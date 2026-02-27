<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductExportController;
use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SalesChannelController;
use App\Http\Controllers\Api\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware(['web', 'throttle:login']);
    Route::post('/token', [AuthController::class, 'tokenLogin'])->middleware('throttle:login');

    Route::middleware(['web'])->group(function () {
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
    Route::post('/products/import', ProductImportController::class);
    Route::get('/products/export', ProductExportController::class);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::post('/products/{product}/archive', [ProductController::class, 'archive']);
    Route::get('/sales-channels', [SalesChannelController::class, 'index']);
    Route::get('/sales-channels/{salesChannel}', [SalesChannelController::class, 'show']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{order}', [OrderController::class, 'update']);
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);
    Route::post('/orders/{order}/ready-to-ship', [OrderController::class, 'readyToShip']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);

    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show']);
    Route::post('/orders/{order}/shipments', [ShipmentController::class, 'store']);
    Route::post('/shipments/{shipment}/delivered', [ShipmentController::class, 'markDelivered']);
    Route::post('/shipments/{shipment}/returned', [ShipmentController::class, 'markReturned']);
    Route::post('/shipments/{shipment}/unpaid', [ShipmentController::class, 'markUnpaid']);

    Route::get('/returns', [ReturnController::class, 'index']);
    Route::get('/returns/{return}', [ReturnController::class, 'show']);
    Route::get('/orders/{order}/return', [ReturnController::class, 'showByOrder']);
    Route::post('/returns/{return}/items', [ReturnController::class, 'addItem']);
    Route::post('/returns/{return}/restock', [ReturnController::class, 'restock']);

    Route::get('/reports/orders/summary', [ReportController::class, 'ordersSummary']);
    Route::get('/reports/inventory/summary', [ReportController::class, 'inventorySummary']);
    Route::get('/reports/returns/summary', [ReportController::class, 'returnsSummary']);
    Route::get('/dashboard', [DashboardController::class, 'show']);
});
