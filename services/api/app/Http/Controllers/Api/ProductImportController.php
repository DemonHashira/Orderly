<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductImportRequest;
use App\Services\Import\ProductImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductImportService $productImportService,
    ) {}

    public function __invoke(ProductImportRequest $request): JsonResponse
    {
        Gate::authorize('products.import');

        $summary = $this->productImportService->import(
            file: $request->file('file'),
            organizationId: (int) $request->user()->organization_id,
        );

        return response()->json($summary);
    }
}
