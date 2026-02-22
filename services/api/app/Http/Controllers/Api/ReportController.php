<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportRangeRequest;
use App\Http\Resources\Reports\InventorySummaryResource;
use App\Http\Resources\Reports\OrdersSummaryResource;
use App\Http\Resources\Reports\ReturnsSummaryResource;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function ordersSummary(ReportRangeRequest $request): JsonResponse
    {
        Gate::authorize('reports.orders.view');

        $generatedAt = now()->utc()->toISOString();
        [$from, $to] = $this->resolveRange($request);

        $summary = $this->reportService->getOrdersSummary(
            organizationId: (int) $request->user()->organization_id,
            from: $from,
            to: $to,
        );

        return response()->json([
            'data' => new OrdersSummaryResource($summary)->resolve($request),
            'meta' => [
                'generated_at' => $generatedAt,
            ],
        ]);
    }

    public function inventorySummary(ReportRangeRequest $request): JsonResponse
    {
        Gate::authorize('reports.inventory.view');

        $generatedAt = now()->utc()->toISOString();
        [$from, $to] = $this->resolveRange($request);

        $summary = $this->reportService->getInventorySummary(
            organizationId: (int) $request->user()->organization_id,
            from: $from,
            to: $to,
        );

        return response()->json([
            'data' => new InventorySummaryResource($summary)->resolve($request),
            'meta' => [
                'generated_at' => $generatedAt,
            ],
        ]);
    }

    public function returnsSummary(ReportRangeRequest $request): JsonResponse
    {
        Gate::authorize('reports.returns.view');

        $generatedAt = now()->utc()->toISOString();
        [$from, $to] = $this->resolveRange($request);

        $summary = $this->reportService->getReturnsSummary(
            organizationId: (int) $request->user()->organization_id,
            from: $from,
            to: $to,
        );

        return response()->json([
            'data' => new ReturnsSummaryResource($summary)->resolve($request),
            'meta' => [
                'generated_at' => $generatedAt,
            ],
        ]);
    }

    private function resolveRange(ReportRangeRequest $request): array
    {
        $from = $request->filled('from') ? Carbon::parse((string) $request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse((string) $request->query('to')) : null;

        return [$from, $to];
    }
}
