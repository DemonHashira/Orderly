import { formatCurrency, formatDateTime, formatNumber } from '@/lib/formatters'
import type { DashboardKpiCard } from '@/features/dashboard/types'
import { mapCountMapToChart, mapInventoryFlowToChart } from '@/features/dashboard/ui/chart-adapters'
import type {
  InventoryReportSummary,
  OrdersReportSummary,
  ReportComparisonMetric,
  ReportComparisonMetricViewModel,
  ReportInsightCard,
  ReportTableCell,
  ReportTableSection,
  ReturnsReportSummary,
} from '@/features/reports/model/report-types'
const headline = (value: string): string =>
  value.replace(/[_-]/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase())

const defaultCell = (value: string): ReportTableCell => ({ value })
const mutedCell = (value: string): ReportTableCell => ({ value, tone: 'muted' })
const monoCell = (value: string): ReportTableCell => ({ value, tone: 'mono' })
const badgeCell = (
  value: string,
  badgeVariant: ReportTableCell['badgeVariant'] = 'secondary',
): ReportTableCell => ({
  value,
  tone: 'badge',
  badgeVariant,
})

const buildDeltaText = (metric: ReportComparisonMetric, money = false): string => {
  if (metric.direction === 'flat') {
    return 'No change'
  }

  const prefix = metric.direction === 'up' ? '+' : ''
  const formatted = money ? formatCurrency(metric.delta) : formatNumber(metric.delta)
  return `${prefix}${formatted}`
}

const buildDeltaPercentageLabel = (value: number | null): string | null => {
  if (value == null) {
    return null
  }

  const prefix = value > 0 ? '+' : ''
  return `${prefix}${value.toFixed(1)}%`
}

const buildComparisonMetric = (
  id: string,
  label: string,
  metric: ReportComparisonMetric | undefined,
  formatter: (value: number | string) => string,
): ReportComparisonMetricViewModel | null => {
  if (!metric) {
    return null
  }

  return {
    id,
    label,
    currentValue: formatter(metric.current),
    previousValue: formatter(metric.previous),
    deltaValue: buildDeltaText(metric, formatter === formatCurrency),
    direction: metric.direction,
    deltaPercentageLabel: buildDeltaPercentageLabel(metric.delta_percentage),
  }
}

const buildComparisonRangeLabel = (
  previousRange: { from: string | null; to: string | null } | undefined,
): string | null => {
  if (!previousRange?.from || !previousRange?.to) {
    return null
  }

  return `${previousRange.from} to ${previousRange.to}`
}

export const buildOrdersReportViewModel = (
  summary: OrdersReportSummary,
): {
  cards: DashboardKpiCard[]
  chartPoints: Array<{ label: string; value: number }>
  comparisonMetrics: ReportComparisonMetricViewModel[]
  comparisonRangeLabel: string | null
  breakdownSections: ReportTableSection[]
  exceptionSections: ReportTableSection[]
  actionLinks: OrdersReportSummary['actions']
  zeroStateMessage: string | null
} => {
  const byChannel = summary.breakdowns?.by_channel ?? []
  const topProducts = summary.breakdowns?.top_products ?? []
  const backlogOrders = summary.exceptions?.backlog_orders ?? []

  const comparisonMetrics = [
    buildComparisonMetric(
      'total-orders',
      'Total orders',
      summary.comparison?.metrics.total_orders,
      formatNumber,
    ),
    buildComparisonMetric(
      'total-revenue',
      'Total revenue',
      summary.comparison?.metrics.total_revenue,
      formatCurrency,
    ),
    buildComparisonMetric(
      'average-order-value',
      'Average order value',
      summary.comparison?.metrics.avg_order_value,
      formatCurrency,
    ),
  ].filter((metric): metric is ReportComparisonMetricViewModel => metric !== null)

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
    comparisonMetrics,
    comparisonRangeLabel: buildComparisonRangeLabel(summary.comparison?.previous_range),
    breakdownSections: [
      {
        id: 'orders-by-channel',
        title: 'Channel mix',
        description: 'Where order volume is coming from in the selected range.',
        columns: [
          { key: 'channel', label: 'Channel' },
          { key: 'orders', label: 'Orders', align: 'right' },
        ],
        rows: byChannel.map((entry) => ({
          channel: defaultCell(entry.label),
          orders: monoCell(formatNumber(entry.value)),
        })),
        emptyMessage: 'No channel activity was recorded for the selected range.',
      },
      {
        id: 'orders-top-products',
        title: 'Top products',
        description: 'Highest-volume ordered products in the selected range.',
        columns: [
          { key: 'product', label: 'Product' },
          { key: 'sku', label: 'SKU' },
          { key: 'quantity', label: 'Units', align: 'right' },
          { key: 'revenue', label: 'Revenue', align: 'right' },
        ],
        rows: topProducts.map((entry) => ({
          product: defaultCell(entry.name),
          sku: monoCell(entry.sku),
          quantity: monoCell(formatNumber(entry.quantity)),
          revenue: monoCell(formatCurrency(entry.revenue)),
        })),
        emptyMessage: 'No product sales were recorded for the selected range.',
      },
    ],
    exceptionSections: [
      {
        id: 'orders-backlog',
        title: 'Backlog orders',
        description: 'Oldest actionable orders that still need operational follow-through.',
        columns: [
          { key: 'reference', label: 'Reference' },
          { key: 'status', label: 'Status' },
          { key: 'customer', label: 'Customer' },
          { key: 'age', label: 'Age', align: 'right' },
          { key: 'amount', label: 'Amount', align: 'right' },
        ],
        rows: backlogOrders.map((entry) => ({
          reference: monoCell(entry.reference),
          status: badgeCell(headline(entry.status), 'outline'),
          customer: defaultCell(entry.customer_name),
          age: mutedCell(`${formatNumber(entry.age_days)} days`),
          amount: monoCell(formatCurrency(entry.total_amount)),
        })),
        emptyMessage: 'No backlog exceptions were found for the selected range.',
      },
    ],
    actionLinks: summary.actions ?? [],
    zeroStateMessage:
      summary.total_orders === 0 ? 'No order activity was recorded for the selected range.' : null,
  }
}

export const buildInventoryReportViewModel = (
  summary: InventoryReportSummary,
): {
  cards: DashboardKpiCard[]
  chartPoints: Array<{ label: string; value: number }>
  comparisonMetrics: ReportComparisonMetricViewModel[]
  comparisonRangeLabel: string | null
  overviewCards: ReportInsightCard[]
  breakdownSections: ReportTableSection[]
  exceptionSections: ReportTableSection[]
  actionLinks: InventoryReportSummary['actions']
  zeroStateMessage: string | null
} => {
  const byMovementType = summary.breakdowns?.by_movement_type ?? []
  const byReferenceType = summary.breakdowns?.by_reference_type ?? []
  const attentionItems = summary.exceptions?.attention_items ?? []

  const comparisonMetrics = [
    buildComparisonMetric(
      'total-available',
      'Available units',
      summary.comparison?.metrics.total_available,
      formatNumber,
    ),
    buildComparisonMetric(
      'low-stock-count',
      'Low stock alerts',
      summary.comparison?.metrics.low_stock_count,
      formatNumber,
    ),
    buildComparisonMetric(
      'movement-in',
      'Movement in',
      summary.comparison?.metrics.movement_in_qty,
      formatNumber,
    ),
    buildComparisonMetric(
      'movement-out',
      'Movement out',
      summary.comparison?.metrics.movement_out_qty,
      formatNumber,
    ),
  ].filter((metric): metric is ReportComparisonMetricViewModel => metric !== null)

  return {
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
    comparisonMetrics,
    comparisonRangeLabel: buildComparisonRangeLabel(summary.comparison?.previous_range),
    overviewCards: [
      {
        id: 'reserved',
        label: 'Reserved',
        value: formatNumber(summary.total_reserved),
        description: 'Units already committed to open demand.',
      },
      {
        id: 'net-movement',
        label: 'Net movement',
        value: formatNumber(summary.movement_in_qty - summary.movement_out_qty),
        description: 'Inbound quantity minus outbound quantity in the selected range.',
      },
      {
        id: 'low-stock',
        label: 'Low stock',
        value: formatNumber(summary.low_stock_count),
        description: 'Active inventory rows at or below threshold.',
      },
    ],
    breakdownSections: [
      {
        id: 'inventory-movement-type',
        title: 'Movement type',
        description: 'Most significant stock movement categories in the selected range.',
        columns: [
          { key: 'type', label: 'Type' },
          { key: 'quantity', label: 'Quantity', align: 'right' },
        ],
        rows: byMovementType.map((entry) => ({
          type: defaultCell(entry.label),
          quantity: monoCell(formatNumber(entry.value)),
        })),
        emptyMessage: 'No inventory movements were recorded for the selected range.',
      },
      {
        id: 'inventory-reference-source',
        title: 'Reference source',
        description: 'Which upstream workflows are driving inventory movements.',
        columns: [
          { key: 'source', label: 'Source' },
          { key: 'quantity', label: 'Quantity', align: 'right' },
        ],
        rows: byReferenceType.map((entry) => ({
          source: defaultCell(entry.label),
          quantity: monoCell(formatNumber(entry.value)),
        })),
        emptyMessage: 'No reference sources were recorded for the selected range.',
      },
    ],
    exceptionSections: [
      {
        id: 'inventory-attention',
        title: 'Inventory attention',
        description: 'Items that are low, depleted, or over-reserved right now.',
        columns: [
          { key: 'product', label: 'Product' },
          { key: 'status', label: 'Status' },
          { key: 'available', label: 'Available', align: 'right' },
          { key: 'shortage', label: 'Shortage', align: 'right' },
        ],
        rows: attentionItems.map((entry) => ({
          product: defaultCell(entry.name),
          status: badgeCell(
            headline(entry.status),
            entry.status === 'out_of_stock' ? 'destructive' : 'outline',
          ),
          available: monoCell(formatNumber(entry.qty_available)),
          shortage: monoCell(formatNumber(entry.shortage_qty)),
        })),
        emptyMessage: 'No inventory exceptions need attention right now.',
      },
    ],
    actionLinks: summary.actions ?? [],
    zeroStateMessage:
      summary.total_skus === 0 && summary.movement_in_qty === 0 && summary.movement_out_qty === 0
        ? 'No inventory activity was recorded for the selected range.'
        : null,
  }
}

export const buildReturnsReportViewModel = (
  summary: ReturnsReportSummary,
): {
  cards: DashboardKpiCard[]
  chartPoints: Array<{ label: string; value: number }>
  comparisonMetrics: ReportComparisonMetricViewModel[]
  comparisonRangeLabel: string | null
  overviewCards: ReportInsightCard[]
  breakdownSections: ReportTableSection[]
  exceptionSections: ReportTableSection[]
  actionLinks: ReturnsReportSummary['actions']
  zeroStateMessage: string | null
} => {
  const totalItems = summary.total_return_items_qty
  const restockRate = totalItems > 0 ? (summary.restockable_items_qty / totalItems) * 100 : 0
  const writeOffRate = totalItems > 0 ? (summary.non_restockable_items_qty / totalItems) * 100 : 0
  const byReason = summary.breakdowns?.by_reason ?? []
  const byChannel = summary.breakdowns?.by_channel ?? []
  const topProducts = summary.breakdowns?.top_products ?? []
  const pendingRestock = summary.exceptions?.pending_restock ?? []
  const writeOffProducts = summary.exceptions?.write_off_products ?? []

  const comparisonMetrics = [
    buildComparisonMetric(
      'total-returns',
      'Total returns',
      summary.comparison?.metrics.total_returns,
      formatNumber,
    ),
    buildComparisonMetric(
      'restockable-items',
      'Restockable items',
      summary.comparison?.metrics.restockable_items_qty,
      formatNumber,
    ),
    buildComparisonMetric(
      'non-restockable-items',
      'Non-restockable items',
      summary.comparison?.metrics.non_restockable_items_qty,
      formatNumber,
    ),
  ].filter((metric): metric is ReportComparisonMetricViewModel => metric !== null)

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
    comparisonMetrics,
    comparisonRangeLabel: buildComparisonRangeLabel(summary.comparison?.previous_range),
    overviewCards: [
      {
        id: 'restock-rate',
        label: 'Restock rate',
        value: `${restockRate.toFixed(1)}%`,
        description: 'Returned quantity that can move back into stock.',
      },
      {
        id: 'write-off-rate',
        label: 'Write-off rate',
        value: `${writeOffRate.toFixed(1)}%`,
        description: 'Returned quantity that has to be written off.',
      },
      {
        id: 'status-rows',
        label: 'Order status buckets',
        value: formatNumber(Object.keys(summary.by_order_status).length),
        description: 'Distinct order statuses among orders with returns in this range.',
      },
    ],
    breakdownSections: [
      {
        id: 'returns-reasons',
        title: 'Return reasons',
        description: 'Top drivers behind return requests in the selected range.',
        columns: [
          { key: 'reason', label: 'Reason' },
          { key: 'returns', label: 'Returns', align: 'right' },
        ],
        rows: byReason.map((entry) => ({
          reason: defaultCell(entry.label),
          returns: monoCell(formatNumber(entry.value)),
        })),
        emptyMessage: 'No return reasons were recorded for the selected range.',
      },
      {
        id: 'returns-channel',
        title: 'Channel mix',
        description: 'Which sales channels are contributing the most return volume.',
        columns: [
          { key: 'channel', label: 'Channel' },
          { key: 'returns', label: 'Returns', align: 'right' },
        ],
        rows: byChannel.map((entry) => ({
          channel: defaultCell(entry.label),
          returns: monoCell(formatNumber(entry.value)),
        })),
        emptyMessage: 'No channel-level return activity was recorded for the selected range.',
      },
      {
        id: 'returns-top-products',
        title: 'Returned products',
        description: 'Products driving the highest returned quantity in the selected range.',
        columns: [
          { key: 'product', label: 'Product' },
          { key: 'sku', label: 'SKU' },
          { key: 'quantity', label: 'Qty', align: 'right' },
        ],
        rows: topProducts.map((entry) => ({
          product: defaultCell(entry.name),
          sku: monoCell(entry.sku),
          quantity: monoCell(formatNumber(entry.quantity)),
        })),
        emptyMessage: 'No returned products were recorded for the selected range.',
      },
    ],
    exceptionSections: [
      {
        id: 'returns-pending-restock',
        title: 'Pending restock',
        description: 'Restockable return orders that still need inventory movement follow-through.',
        columns: [
          { key: 'reference', label: 'Order' },
          { key: 'customer', label: 'Customer' },
          { key: 'reason', label: 'Reason' },
          { key: 'quantity', label: 'Qty', align: 'right' },
        ],
        rows: pendingRestock.map((entry) => ({
          reference: monoCell(entry.order_reference),
          customer: defaultCell(entry.customer_name),
          reason: mutedCell(entry.reason || 'Unspecified'),
          quantity: monoCell(formatNumber(entry.restockable_qty)),
        })),
        emptyMessage: 'No pending restock returns were found for the selected range.',
      },
      {
        id: 'returns-write-off-products',
        title: 'Write-off products',
        description: 'Products accumulating the most non-restockable returned quantity.',
        columns: [
          { key: 'product', label: 'Product' },
          { key: 'sku', label: 'SKU' },
          { key: 'quantity', label: 'Qty', align: 'right' },
        ],
        rows: writeOffProducts.map((entry) => ({
          product: defaultCell(entry.name),
          sku: monoCell(entry.sku),
          quantity: monoCell(formatNumber(entry.quantity)),
        })),
        emptyMessage: 'No write-off hotspots were found for the selected range.',
      },
    ],
    actionLinks: summary.actions ?? [],
    zeroStateMessage:
      summary.total_returns === 0 ? 'No returns recorded for the selected range.' : null,
  }
}

export const formatReportTimestamp = (value: string): string => formatDateTime(value)
