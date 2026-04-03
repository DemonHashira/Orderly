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
        $summary = $this->getOrdersBaseSummary($organizationId, $from, $to);

        return [
            ...$summary,
            ...$this->buildOrdersComparison(
                organizationId: $organizationId,
                from: $from,
                to: $to,
                totalOrders: $summary['total_orders'],
                totalRevenue: $summary['total_revenue'],
                avgOrderValue: $summary['avg_order_value'],
            ),
            'breakdowns' => [
                'by_channel' => $this->getOrdersByChannel($organizationId, $from, $to),
                'top_products' => $this->getTopOrderedProducts($organizationId, $from, $to),
            ],
            'exceptions' => [
                'backlog_orders' => $this->getOrdersBacklogExceptions($organizationId, $from, $to),
            ],
            'actions' => [
                $this->buildAction(
                    id: 'open-orders-backlog',
                    label: 'Open backlog orders',
                    description: 'Review draft, confirmed, and ready-to-ship orders.',
                    path: '/orders',
                    query: $this->buildOrdersActionQuery($from, $to),
                ),
            ],
        ];
    }

    public function getInventorySummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $summary = $this->getInventoryBaseSummary($organizationId, $from, $to);

        return [
            ...$summary,
            ...$this->buildInventoryComparison(
                organizationId: $organizationId,
                from: $from,
                to: $to,
                totalAvailable: $summary['total_available'],
                lowStockCount: $summary['low_stock_count'],
                movementInQty: $summary['movement_in_qty'],
                movementOutQty: $summary['movement_out_qty'],
            ),
            'breakdowns' => [
                'by_movement_type' => $this->getInventoryByMovementType($organizationId, $from, $to),
                'by_reference_type' => $this->getInventoryByReferenceType($organizationId, $from, $to),
            ],
            'exceptions' => [
                'attention_items' => $this->getInventoryAttentionItems($organizationId),
            ],
            'actions' => [
                $this->buildAction(
                    id: 'open-low-stock-items',
                    label: 'Open low stock items',
                    description: 'Review items that need replenishment.',
                    path: '/inventory/stocks',
                    query: [],
                ),
            ],
        ];
    }

    public function getReturnsSummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $summary = $this->getReturnsBaseSummary($organizationId, $from, $to);

        return [
            ...$summary,
            ...$this->buildReturnsComparison(
                organizationId: $organizationId,
                from: $from,
                to: $to,
                totalReturns: $summary['total_returns'],
                restockableItemsQty: $summary['restockable_items_qty'],
                nonRestockableItemsQty: $summary['non_restockable_items_qty'],
            ),
            'breakdowns' => [
                'by_reason' => $this->getReturnsByReason($organizationId, $from, $to),
                'by_channel' => $this->getReturnsByChannel($organizationId, $from, $to),
                'top_products' => $this->getTopReturnedProducts($organizationId, $from, $to),
            ],
            'exceptions' => [
                'pending_restock' => $this->getPendingRestockReturns($organizationId, $from, $to),
                'write_off_products' => $this->getWriteOffProducts($organizationId, $from, $to),
            ],
            'actions' => [
                $this->buildAction(
                    id: 'open-restock-queue',
                    label: 'Open restock queue',
                    description: 'Review returns that still have restockable quantity.',
                    path: '/returns',
                    query: $this->buildReturnsActionQuery($from, $to),
                ),
            ],
        ];
    }

    public function getDashboardSummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return [
            'range' => $this->rangePayload($from, $to),
            'orders' => $this->getOrdersBaseSummary($organizationId, $from, $to),
            'inventory' => $this->getInventoryBaseSummary($organizationId, $from, $to),
            'returns' => $this->getReturnsBaseSummary($organizationId, $from, $to),
        ];
    }

    /**
     * @return array{
     *     range: array{from: ?string, to: ?string, is_all_time: bool},
     *     total_orders: int,
     *     total_revenue: string,
     *     avg_order_value: string,
     *     by_status: array<string, int>,
     * }
     */
    private function getOrdersBaseSummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
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

    /**
     * @return array{
     *     range: array{from: ?string, to: ?string, is_all_time: bool},
     *     total_skus: int,
     *     total_on_hand: int,
     *     total_reserved: int,
     *     total_available: int,
     *     low_stock_count: int,
     *     movement_in_qty: int,
     *     movement_out_qty: int,
     * }
     */
    private function getInventoryBaseSummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
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

    /**
     * @return array{
     *     range: array{from: ?string, to: ?string, is_all_time: bool},
     *     total_returns: int,
     *     total_return_items_qty: int,
     *     restockable_items_qty: int,
     *     non_restockable_items_qty: int,
     *     by_order_status: array<string, int>,
     * }
     */
    private function getReturnsBaseSummary(int $organizationId, ?Carbon $from = null, ?Carbon $to = null): array
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

    private function buildOrdersComparison(
        int $organizationId,
        ?Carbon $from,
        ?Carbon $to,
        int $totalOrders,
        string|int|float $totalRevenue,
        string|int|float $avgOrderValue,
    ): array {
        $previousRange = $this->resolvePreviousRange($from, $to);
        if ($previousRange === null) {
            return [];
        }

        [$previousFrom, $previousTo] = $previousRange;
        $previousSummary = $this->getOrdersBaseSummary($organizationId, $previousFrom, $previousTo);

        return [
            'comparison' => [
                'previous_range' => $this->compactRangePayload($previousFrom, $previousTo),
                'metrics' => [
                    'total_orders' => $this->buildComparisonMetric($totalOrders, $previousSummary['total_orders']),
                    'total_revenue' => $this->buildComparisonMetric($totalRevenue, $previousSummary['total_revenue'], true),
                    'avg_order_value' => $this->buildComparisonMetric($avgOrderValue, $previousSummary['avg_order_value'], true),
                ],
            ],
        ];
    }

    private function buildInventoryComparison(
        int $organizationId,
        ?Carbon $from,
        ?Carbon $to,
        int $totalAvailable,
        int $lowStockCount,
        int $movementInQty,
        int $movementOutQty,
    ): array {
        $previousRange = $this->resolvePreviousRange($from, $to);
        if ($previousRange === null) {
            return [];
        }

        [$previousFrom, $previousTo] = $previousRange;
        $previousSummary = $this->getInventoryBaseSummary($organizationId, $previousFrom, $previousTo);

        return [
            'comparison' => [
                'previous_range' => $this->compactRangePayload($previousFrom, $previousTo),
                'metrics' => [
                    'total_available' => $this->buildComparisonMetric($totalAvailable, $previousSummary['total_available']),
                    'low_stock_count' => $this->buildComparisonMetric($lowStockCount, $previousSummary['low_stock_count']),
                    'movement_in_qty' => $this->buildComparisonMetric($movementInQty, $previousSummary['movement_in_qty']),
                    'movement_out_qty' => $this->buildComparisonMetric($movementOutQty, $previousSummary['movement_out_qty']),
                ],
            ],
        ];
    }

    private function buildReturnsComparison(
        int $organizationId,
        ?Carbon $from,
        ?Carbon $to,
        int $totalReturns,
        int $restockableItemsQty,
        int $nonRestockableItemsQty,
    ): array {
        $previousRange = $this->resolvePreviousRange($from, $to);
        if ($previousRange === null) {
            return [];
        }

        [$previousFrom, $previousTo] = $previousRange;
        $previousSummary = $this->getReturnsBaseSummary($organizationId, $previousFrom, $previousTo);

        return [
            'comparison' => [
                'previous_range' => $this->compactRangePayload($previousFrom, $previousTo),
                'metrics' => [
                    'total_returns' => $this->buildComparisonMetric($totalReturns, $previousSummary['total_returns']),
                    'restockable_items_qty' => $this->buildComparisonMetric($restockableItemsQty, $previousSummary['restockable_items_qty']),
                    'non_restockable_items_qty' => $this->buildComparisonMetric($nonRestockableItemsQty, $previousSummary['non_restockable_items_qty']),
                ],
            ],
        ];
    }

    private function resolvePreviousRange(?Carbon $from, ?Carbon $to): ?array
    {
        if ($from === null || $to === null) {
            return null;
        }

        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $from->copy()->subDays($days)->startOfDay();

        return [$previousFrom, $previousTo];
    }

    private function compactRangePayload(Carbon $from, Carbon $to): array
    {
        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    private function buildComparisonMetric(int|float|string $current, int|float|string $previous, bool $money = false): array
    {
        $currentNumeric = $this->numericValue($current);
        $previousNumeric = $this->numericValue($previous);
        $deltaNumeric = $currentNumeric - $previousNumeric;

        return [
            'current' => $money ? $this->formatMoney($current) : (int) round($currentNumeric),
            'previous' => $money ? $this->formatMoney($previous) : (int) round($previousNumeric),
            'delta' => $money ? $this->formatMoney($deltaNumeric) : (int) round($deltaNumeric),
            'direction' => $deltaNumeric > 0 ? 'up' : ($deltaNumeric < 0 ? 'down' : 'flat'),
            'delta_percentage' => $previousNumeric === 0.0
                ? ($currentNumeric === 0.0 ? 0.0 : null)
                : round(($deltaNumeric / $previousNumeric) * 100, 1),
        ];
    }

    private function getOrdersByChannel(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = Order::query()
            ->forOrg($organizationId)
            ->join('sales_channels', 'sales_channels.id', '=', 'orders.sales_channel_id');

        $this->applyDateRange($query, $from, $to, 'orders.created_at');

        return $query
            ->selectRaw('sales_channels.name as label, COUNT(*) as value')
            ->groupBy('sales_channels.name')
            ->orderByDesc('value')
            ->orderBy('sales_channels.name')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->all();
    }

    private function getTopOrderedProducts(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.organization_id', $organizationId);

        $this->applyDateRange($query, $from, $to, 'orders.created_at');

        return $query
            ->selectRaw('products.id as product_id, products.name, products.sku')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(order_items.total_price), 0) as revenue')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('quantity')
            ->orderBy('products.name')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'sku' => (string) $row->sku,
                'quantity' => (int) $row->quantity,
                'revenue' => $this->formatMoney((string) $row->revenue),
            ])
            ->all();
    }

    private function getOrdersBacklogExceptions(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = Order::query()
            ->forOrg($organizationId)
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereIn('orders.current_status', ['draft', 'confirmed', 'ready_to_ship']);

        $this->applyDateRange($query, $from, $to, 'orders.created_at');

        $referenceDate = $to?->copy()->endOfDay() ?? now();

        return $query
            ->selectRaw('orders.id as order_id, orders.reference, orders.current_status as status, customers.first_name, customers.middle_name, customers.last_name, orders.created_at, orders.total_amount')
            ->orderBy('orders.created_at')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'order_id' => (int) $row->order_id,
                'reference' => (string) $row->reference,
                'status' => (string) $row->status,
                'customer_name' => $this->formatCustomerName(
                    $row->first_name,
                    $row->middle_name,
                    $row->last_name,
                ),
                'created_at' => Carbon::parse((string) $row->created_at)->toISOString(),
                'age_days' => Carbon::parse((string) $row->created_at)->startOfDay()->diffInDays($referenceDate->copy()->startOfDay()),
                'total_amount' => $this->formatMoney((string) $row->total_amount),
            ])
            ->all();
    }

    private function buildOrdersActionQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'status' => 'ready_to_ship',
            'created_from' => $from?->toDateString(),
            'created_to' => $to?->toDateString(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function getInventoryByMovementType(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = InventoryMovement::query()->forOrg($organizationId);
        $this->applyDateRange($query, $from, $to, 'inventory_movements.created_at');

        return $query
            ->selectRaw('inventory_movements.type as label, COALESCE(SUM(ABS(qty_delta)), 0) as value')
            ->groupBy('inventory_movements.type')
            ->orderByDesc('value')
            ->orderBy('inventory_movements.type')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'label' => $this->headline((string) $row->label),
                'value' => (int) $row->value,
            ])
            ->all();
    }

    private function getInventoryByReferenceType(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = InventoryMovement::query()->forOrg($organizationId);
        $this->applyDateRange($query, $from, $to, 'inventory_movements.created_at');

        return $query
            ->selectRaw("COALESCE(inventory_movements.reference_type, 'Manual') as label, COALESCE(SUM(ABS(qty_delta)), 0) as value")
            ->groupBy('inventory_movements.reference_type')
            ->orderByDesc('value')
            ->orderBy('label')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'label' => $this->headline((string) $row->label),
                'value' => (int) $row->value,
            ])
            ->all();
    }

    private function getInventoryAttentionItems(int $organizationId): array
    {
        return InventoryStock::query()
            ->forOrg($organizationId)
            ->join('products', 'products.id', '=', 'inventory_stocks.product_id')
            ->where(function (EloquentBuilder|QueryBuilder $query): void {
                $query
                    ->whereColumn('qty_on_hand', '<', 'qty_reserved')
                    ->orWhere('qty_on_hand', '=', 0)
                    ->orWhere(function (EloquentBuilder|QueryBuilder $nested): void {
                        $nested
                            ->whereNotNull('reorder_threshold')
                            ->whereColumn('qty_on_hand', '<=', 'reorder_threshold');
                    });
            })
            ->selectRaw('products.id as product_id, products.name, products.sku, qty_on_hand, qty_reserved, (qty_on_hand - qty_reserved) as qty_available, reorder_threshold')
            ->orderByRaw('CASE WHEN qty_on_hand < qty_reserved THEN 0 WHEN qty_on_hand = 0 THEN 1 ELSE 2 END')
            ->orderByRaw('CASE WHEN reorder_threshold IS NULL THEN 0 WHEN reorder_threshold > qty_on_hand THEN reorder_threshold - qty_on_hand ELSE 0 END DESC')
            ->limit(5)
            ->get()
            ->map(function (object $row): array {
                $status = 'low_stock';
                if ((int) $row->qty_on_hand < (int) $row->qty_reserved) {
                    $status = 'over_reserved';
                } elseif ((int) $row->qty_on_hand === 0) {
                    $status = 'out_of_stock';
                }

                $shortageQty = $status === 'over_reserved'
                    ? max(0, (int) $row->qty_reserved - (int) $row->qty_on_hand)
                    : max(0, (int) ($row->reorder_threshold ?? 0) - (int) $row->qty_on_hand);

                return [
                    'product_id' => (int) $row->product_id,
                    'name' => (string) $row->name,
                    'sku' => (string) $row->sku,
                    'status' => $status,
                    'qty_on_hand' => (int) $row->qty_on_hand,
                    'qty_reserved' => (int) $row->qty_reserved,
                    'qty_available' => (int) $row->qty_available,
                    'reorder_threshold' => $row->reorder_threshold !== null ? (int) $row->reorder_threshold : null,
                    'shortage_qty' => $shortageQty,
                ];
            })
            ->all();
    }

    private function getReturnsByReason(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = ReturnOrder::query()
            ->join('orders', 'orders.id', '=', 'return_orders.order_id')
            ->where('orders.organization_id', $organizationId);

        $this->applyReturnsDateRange($query, $from, $to);

        return $query
            ->selectRaw("COALESCE(return_orders.reason, 'Unspecified') as label, COUNT(return_orders.id) as value")
            ->groupBy('return_orders.reason')
            ->orderByDesc('value')
            ->orderBy('label')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->all();
    }

    private function getReturnsByChannel(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = ReturnOrder::query()
            ->join('orders', 'orders.id', '=', 'return_orders.order_id')
            ->join('sales_channels', 'sales_channels.id', '=', 'orders.sales_channel_id')
            ->where('orders.organization_id', $organizationId);

        $this->applyReturnsDateRange($query, $from, $to);

        return $query
            ->selectRaw('sales_channels.name as label, COUNT(return_orders.id) as value')
            ->groupBy('sales_channels.name')
            ->orderByDesc('value')
            ->orderBy('sales_channels.name')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->all();
    }

    private function getTopReturnedProducts(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = DB::table('return_items')
            ->join('return_orders', 'return_orders.id', '=', 'return_items.return_id')
            ->join('orders', 'orders.id', '=', 'return_orders.order_id')
            ->join('products', 'products.id', '=', 'return_items.product_id')
            ->where('orders.organization_id', $organizationId);

        $this->applyReturnsDateRange($query, $from, $to);

        return $query
            ->selectRaw('products.id as product_id, products.name, products.sku, COALESCE(SUM(return_items.quantity), 0) as quantity')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('quantity')
            ->orderBy('products.name')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'sku' => (string) $row->sku,
                'quantity' => (int) $row->quantity,
            ])
            ->all();
    }

    private function getPendingRestockReturns(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = DB::table('return_orders')
            ->join('orders', 'orders.id', '=', 'return_orders.order_id')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->join('return_items', 'return_items.return_id', '=', 'return_orders.id')
            ->where('orders.organization_id', $organizationId)
            ->where('return_items.restockable', true);

        $this->applyReturnsDateRange($query, $from, $to);

        return $query
            ->selectRaw('return_orders.id as return_id, orders.reference as order_reference, customers.first_name, customers.middle_name, customers.last_name, COALESCE(return_orders.reason, \'\') as reason')
            ->selectRaw('COALESCE(return_orders.returned_at, return_orders.created_at) as returned_at')
            ->selectRaw('COALESCE(SUM(return_items.quantity), 0) as restockable_qty')
            ->groupBy('return_orders.id', 'orders.reference', 'customers.first_name', 'customers.middle_name', 'customers.last_name', 'return_orders.reason', 'return_orders.returned_at', 'return_orders.created_at')
            ->havingRaw('NOT EXISTS (SELECT 1 FROM inventory_movements WHERE inventory_movements.reference_type = ? AND inventory_movements.reference_id = return_orders.id AND inventory_movements.type = ?)', ['Return', 'return'])
            ->orderBy('returned_at')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'return_id' => (int) $row->return_id,
                'order_reference' => (string) $row->order_reference,
                'reason' => (string) $row->reason,
                'returned_at' => Carbon::parse((string) $row->returned_at)->toISOString(),
                'restockable_qty' => (int) $row->restockable_qty,
                'customer_name' => $this->formatCustomerName(
                    $row->first_name,
                    $row->middle_name,
                    $row->last_name,
                ),
            ])
            ->all();
    }

    private function getWriteOffProducts(int $organizationId, ?Carbon $from, ?Carbon $to): array
    {
        $query = DB::table('return_items')
            ->join('return_orders', 'return_orders.id', '=', 'return_items.return_id')
            ->join('orders', 'orders.id', '=', 'return_orders.order_id')
            ->join('products', 'products.id', '=', 'return_items.product_id')
            ->where('orders.organization_id', $organizationId)
            ->where('return_items.restockable', false);

        $this->applyReturnsDateRange($query, $from, $to);

        return $query
            ->selectRaw('products.id as product_id, products.name, products.sku, COALESCE(SUM(return_items.quantity), 0) as quantity')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('quantity')
            ->orderBy('products.name')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'sku' => (string) $row->sku,
                'quantity' => (int) $row->quantity,
            ])
            ->all();
    }

    private function buildReturnsActionQuery(?Carbon $from, ?Carbon $to): array
    {
        return array_filter([
            'status' => 'restockable',
            'created_from' => $from?->toDateString(),
            'created_to' => $to?->toDateString(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function buildAction(string $id, string $label, string $description, string $path, array $query): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'to' => [
                'path' => $path,
                'query' => $query,
            ],
        ];
    }

    private function formatMoney(string|int|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function numericValue(string|int|float $value): float
    {
        return (float) $value;
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

    private function headline(string $value): string
    {
        return str($value)
            ->replace(['_', '-'], ' ')
            ->headline()
            ->toString();
    }

    private function formatCustomerName(?string $firstName, ?string $middleName, ?string $lastName): string
    {
        return trim(implode(' ', array_filter([
            $firstName,
            $middleName,
            $lastName,
        ])));
    }
}
