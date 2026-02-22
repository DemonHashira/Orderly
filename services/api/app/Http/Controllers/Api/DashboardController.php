<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportRangeRequest;
use App\Http\Resources\Reports\DashboardSummaryResource;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function show(ReportRangeRequest $request): JsonResponse
    {
        Gate::authorize('dashboard.view');

        $generatedAt = now()->utc()->toISOString();
        $from = $request->filled('from') ? Carbon::parse((string) $request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse((string) $request->query('to')) : null;

        $organizationId = (int) $request->user()->organization_id;
        $summary = [
            'range' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'is_all_time' => $from === null && $to === null,
            ],
        ];

        if (Gate::allows('reports.orders.view')) {
            $summary['orders'] = $this->reportService->getOrdersSummary(
                organizationId: $organizationId,
                from: $from,
                to: $to,
            );
        }

        if (Gate::allows('reports.inventory.view')) {
            $summary['inventory'] = $this->reportService->getInventorySummary(
                organizationId: $organizationId,
                from: $from,
                to: $to,
            );
        }

        if (Gate::allows('reports.returns.view')) {
            $summary['returns'] = $this->reportService->getReturnsSummary(
                organizationId: $organizationId,
                from: $from,
                to: $to,
            );
        }

        if (! array_key_exists('orders', $summary) && ! array_key_exists('inventory', $summary) && ! array_key_exists('returns', $summary)) {
            abort(403);
        }

        return response()->json([
            'data' => new DashboardSummaryResource($summary)->resolve($request),
            'meta' => [
                'generated_at' => $generatedAt,
            ],
        ]);
    }
}
