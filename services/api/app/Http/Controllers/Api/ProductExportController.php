<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductExportRequest;
use App\Services\Import\ProductExportService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProductExportController extends Controller
{
    public function __construct(
        private readonly ProductExportService $productExportService,
    ) {}

    public function __invoke(ProductExportRequest $request): BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('products.export');

        $validated = $request->validated();

        return $this->productExportService->export(
            organizationId: (int) $request->user()->organization_id,
            format: (string) ($validated['format'] ?? 'csv'),
            filters: [
                'is_active' => $validated['is_active'] ?? null,
                'q' => $validated['q'] ?? null,
            ],
        );
    }
}
