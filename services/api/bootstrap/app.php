<?php

use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Returns\Exceptions\InvalidReturnItemQuantity;
use App\Domain\Returns\Exceptions\ReturnItemNotInOrder;
use App\Domain\Returns\Exceptions\ReturnQuantityExceeded;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidOrderTransition $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'invalid_order_transition',
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (InsufficientStock $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'insufficient_stock',
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (ReturnItemNotInOrder $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'return_item_not_in_order',
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (ReturnQuantityExceeded $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'return_quantity_exceeded',
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (InvalidReturnItemQuantity $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'invalid_return_item_quantity',
            ], Response::HTTP_CONFLICT);
        });
    })->create();
