import { formatCurrency, formatNumber } from '@/lib/formatters'
import type { DashboardKpiCard } from '@/features/dashboard/types'
import { mapCountMapToChart, mapInventoryFlowToChart } from '@/features/dashboard/ui/chart-adapters'
import type { InventorySummary, OrdersSummary, ReturnsSummary } from '@/types'

const buildCountList = (input: Record<string, number>) =>
  Object.entries(input)
    .map(([label, value]) => ({ label, value }))
    .sort((a, b) => b.value - a.value)

export const buildOrdersReportViewModel = (
  summary: OrdersSummary,
): {
  cards: DashboardKpiCard[]
  chartPoints: Array<{ label: string; value: number }>
  statusBreakdown: Array<{ label: string; value: number }>
  topStatus: { label: string; value: number } | null
} => {
  const statusBreakdown = buildCountList(summary.by_status)

  return {
    cards: [
      {
        id: 'orders-total',
        title: 'Total Orders',
        value: formatNumber(summary.total_orders),
        description: 'Orders created in the selected range',
      },
      {
        id: 'orders-revenue',
        title: 'Total Revenue',
        value: formatCurrency(summary.total_revenue),
        description: 'Total booked revenue across matching orders',
      },
      {
        id: 'orders-average',
        title: 'Average Order Value',
        value: formatCurrency(summary.avg_order_value),
        description: 'Average value per order in the range',
      },
    ],
    chartPoints: mapCountMapToChart(summary.by_status),
    statusBreakdown,
    topStatus: statusBreakdown[0] ?? null,
  }
}

export const buildInventoryReportViewModel = (
  summary: InventorySummary,
): {
  cards: DashboardKpiCard[]
  chartPoints: Array<{ label: string; value: number }>
  netMovement: number
} => ({
  cards: [
    {
      id: 'inventory-skus',
      title: 'Tracked SKUs',
      value: formatNumber(summary.total_skus),
      description: 'Products with stock rows in the organization',
    },
    {
      id: 'inventory-on-hand',
      title: 'On Hand',
      value: formatNumber(summary.total_on_hand),
      description: 'Physical stock currently recorded',
    },
    {
      id: 'inventory-available',
      title: 'Available Units',
      value: formatNumber(summary.total_available),
      description: 'Units available to sell right now',
    },
    {
      id: 'inventory-low-stock',
      title: 'Low Stock Alerts',
      value: formatNumber(summary.low_stock_count),
      description: 'SKUs at or below reorder threshold',
    },
  ],
  chartPoints: mapInventoryFlowToChart(summary.movement_in_qty, summary.movement_out_qty),
  netMovement: summary.movement_in_qty - summary.movement_out_qty,
})

export const buildReturnsReportViewModel = (
  summary: ReturnsSummary,
): {
  cards: DashboardKpiCard[]
  chartPoints: Array<{ label: string; value: number }>
  statusBreakdown: Array<{ label: string; value: number }>
  restockRate: number
  writeOffRate: number
} => {
  const totalItems = summary.total_return_items_qty

  return {
    cards: [
      {
        id: 'returns-total',
        title: 'Total Returns',
        value: formatNumber(summary.total_returns),
        description: 'Return orders created in the selected range',
      },
      {
        id: 'returns-items',
        title: 'Returned Items',
        value: formatNumber(summary.total_return_items_qty),
        description: 'Total returned quantity across all return orders',
      },
      {
        id: 'returns-restockable',
        title: 'Restockable Items',
        value: formatNumber(summary.restockable_items_qty),
        description: 'Returned quantity that can go back to stock',
      },
      {
        id: 'returns-non-restockable',
        title: 'Non-Restockable Items',
        value: formatNumber(summary.non_restockable_items_qty),
        description: 'Returned quantity that must be written off',
      },
    ],
    chartPoints: mapCountMapToChart(summary.by_order_status),
    statusBreakdown: buildCountList(summary.by_order_status),
    restockRate: totalItems > 0 ? (summary.restockable_items_qty / totalItems) * 100 : 0,
    writeOffRate: totalItems > 0 ? (summary.non_restockable_items_qty / totalItems) * 100 : 0,
  }
}
