<?php

namespace App\Services\Reports;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\ReturnOrder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReportService
{
    public function getOrdersSummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $ordersQuery = Order::query()->forOrg($organizationId);
        $this->applyDateRange($ordersQuery, $from, $to, 'orders.created_at');

        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (string) ((clone $ordersQuery)->sum('orders.total_amount') ?: 0);
        $avgOrderValue = (string) ((clone $ordersQuery)->avg('orders.total_amount') ?: 0);

        $statusRows = (clone $ordersQuery)
            ->selectRaw('orders.current_status, COUNT(*) as total')
            ->groupBy('orders.current_status')
            ->pluck('total', 'current_status')
            ->all();

        return [
            'range' => $this->rangePayload($from, $to),
            'total_orders' => $totalOrders,
            'total_revenue' => $this->formatMoney($totalRevenue),
            'avg_order_value' => $this->formatMoney($avgOrderValue),
            'by_status' => $this->normalizeCountMap($statusRows),
        ];
    }

    public function getInventorySummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $stocksQuery = InventoryStock::query()->forOrg($organizationId);

        $totals = (clone $stocksQuery)
            ->selectRaw('COUNT(*) as total_skus')
            ->selectRaw('COALESCE(SUM(qty_on_hand), 0) as total_on_hand')
            ->selectRaw('COALESCE(SUM(qty_reserved), 0) as total_reserved')
            ->selectRaw('COALESCE(SUM(qty_on_hand - qty_reserved), 0) as total_available')
            ->first();

        $lowStockCount = (clone $stocksQuery)
            ->whereNotNull('reorder_threshold')
            ->whereColumn('qty_on_hand', '<=', 'reorder_threshold')
            ->count();

        $movementsQuery = InventoryMovement::query()->forOrg($organizationId);
        $this->applyDateRange($movementsQuery, $from, $to, 'inventory_movements.created_at');

        $movementTotals = (clone $movementsQuery)
            ->selectRaw('COALESCE(SUM(CASE WHEN qty_delta > 0 THEN qty_delta ELSE 0 END), 0) as movement_in_qty')
            ->selectRaw('COALESCE(ABS(SUM(CASE WHEN qty_delta < 0 THEN qty_delta ELSE 0 END)), 0) as movement_out_qty')
            ->first();

        return [
            'range' => $this->rangePayload($from, $to),
            'total_skus' => (int) ($totals?->total_skus ?? 0),
            'total_on_hand' => (int) ($totals?->total_on_hand ?? 0),
            'total_reserved' => (int) ($totals?->total_reserved ?? 0),
            'total_available' => (int) ($totals?->total_available ?? 0),
            'low_stock_count' => $lowStockCount,
            'movement_in_qty' => (int) ($movementTotals?->movement_in_qty ?? 0),
            'movement_out_qty' => (int) ($movementTotals?->movement_out_qty ?? 0),
        ];
    }

    public function getReturnsSummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $returnsQuery = ReturnOrder::query()
            ->join('orders', 'orders.id', '=', 'return_orders.order_id')
            ->where('orders.organization_id', $organizationId);

        $this->applyReturnsDateRange($returnsQuery, $from, $to);

        $totalReturns = (clone $returnsQuery)->count('return_orders.id');

        $itemAggQuery = DB::table('return_items')
            ->join('return_orders', 'return_orders.id', '=', 'return_items.return_id')
            ->join('orders', 'orders.id', '=', 'return_orders.order_id')
            ->where('orders.organization_id', $organizationId);

        $this->applyReturnsDateRange($itemAggQuery, $from, $to);

        $itemTotals = (clone $itemAggQuery)
            ->selectRaw('COALESCE(SUM(return_items.quantity), 0) as total_return_items_qty')
            ->selectRaw('COALESCE(SUM(CASE WHEN return_items.restockable IS TRUE THEN return_items.quantity ELSE 0 END), 0) as restockable_items_qty')
            ->selectRaw('COALESCE(SUM(CASE WHEN return_items.restockable IS FALSE THEN return_items.quantity ELSE 0 END), 0) as non_restockable_items_qty')
            ->first();

        $statusRows = (clone $returnsQuery)
            ->selectRaw('orders.current_status, COUNT(return_orders.id) as total')
            ->groupBy('orders.current_status')
            ->pluck('total', 'orders.current_status')
            ->all();

        return [
            'range' => $this->rangePayload($from, $to),
            'total_returns' => $totalReturns,
            'total_return_items_qty' => (int) ($itemTotals?->total_return_items_qty ?? 0),
            'restockable_items_qty' => (int) ($itemTotals?->restockable_items_qty ?? 0),
            'non_restockable_items_qty' => (int) ($itemTotals?->non_restockable_items_qty ?? 0),
            'by_order_status' => $this->normalizeCountMap($statusRows),
        ];
    }

    public function getDashboardSummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return [
            'range' => $this->rangePayload($from, $to),
            'orders' => $this->getOrdersSummary($organizationId, $from, $to),
            'inventory' => $this->getInventorySummary($organizationId, $from, $to),
            'returns' => $this->getReturnsSummary($organizationId, $from, $to),
        ];
    }

    private function applyDateRange(EloquentBuilder|QueryBuilder $query, ?Carbon $from, ?Carbon $to, string $column): void
    {
        if ($from !== null) {
            $query->where($column, '>=', $from->copy()->startOfDay());
        }

        if ($to !== null) {
            $query->where($column, '<=', $to->copy()->endOfDay());
        }
    }

    private function applyReturnsDateRange(EloquentBuilder|QueryBuilder $query, ?Carbon $from, ?Carbon $to): void
    {
        if ($from !== null) {
            $query->whereRaw('COALESCE(return_orders.returned_at, return_orders.created_at) >= ?', [$from->copy()->startOfDay()]);
        }

        if ($to !== null) {
            $query->whereRaw('COALESCE(return_orders.returned_at, return_orders.created_at) <= ?', [$to->copy()->endOfDay()]);
        }
    }

    private function rangePayload(?Carbon $from, ?Carbon $to): array
    {
        return [
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'is_all_time' => $from === null && $to === null,
        ];
    }

    private function formatMoney(string|int|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function normalizeCountMap(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $key => $value) {
            $normalized[(string) $key] = (int) $value;
        }

        ksort($normalized);

        return $normalized;
    }
}
