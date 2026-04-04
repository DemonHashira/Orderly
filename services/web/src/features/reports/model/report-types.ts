export type ReportRange = {
  from: string | null
  to: string | null
  is_all_time: boolean
}

export type ReportComparisonMetric = {
  current: number | string
  previous: number | string
  delta: number | string
  direction: 'up' | 'down' | 'flat'
  delta_percentage: number | null
}

export type ReportComparison = {
  previous_range: {
    from: string | null
    to: string | null
  }
  metrics: Record<string, ReportComparisonMetric>
}

export type ReportActionLink = {
  id: string
  label: string
  description: string
  to: {
    path: string
    query: Record<string, string>
  }
}

export type OrdersReportSummary = {
  range: ReportRange
  total_orders: number
  total_revenue: string
  avg_order_value: string
  by_status: Record<string, number>
  comparison?: ReportComparison
  breakdowns: {
    by_channel: Array<{ label: string; value: number }>
    top_products: Array<{
      product_id: number
      name: string
      sku: string
      quantity: number
      revenue: string
    }>
  }
  exceptions: {
    backlog_orders: Array<{
      order_id: number
      reference: string
      status: string
      customer_name: string
      created_at: string
      age_days: number
      total_amount: string
    }>
  }
  actions: ReportActionLink[]
}

export type InventoryReportSummary = {
  range: ReportRange
  total_skus: number
  total_on_hand: number
  total_reserved: number
  total_available: number
  low_stock_count: number
  movement_in_qty: number
  movement_out_qty: number
  comparison?: ReportComparison
  breakdowns: {
    by_movement_type: Array<{ label: string; value: number }>
    by_reference_type: Array<{ label: string; value: number }>
  }
  exceptions: {
    attention_items: Array<{
      product_id: number
      name: string
      sku: string
      status: string
      qty_on_hand: number
      qty_reserved: number
      qty_available: number
      reorder_threshold: number | null
      shortage_qty: number
    }>
  }
  actions: ReportActionLink[]
}

export type ReturnsReportSummary = {
  range: ReportRange
  total_returns: number
  total_return_items_qty: number
  restockable_items_qty: number
  non_restockable_items_qty: number
  by_order_status: Record<string, number>
  comparison?: ReportComparison
  breakdowns: {
    by_reason: Array<{ label: string; value: number }>
    by_channel: Array<{ label: string; value: number }>
    top_products: Array<{
      product_id: number
      name: string
      sku: string
      quantity: number
    }>
  }
  exceptions: {
    pending_restock: Array<{
      return_id: number
      order_reference: string
      reason: string
      returned_at: string
      restockable_qty: number
      customer_name: string
    }>
    write_off_products: Array<{
      product_id: number
      name: string
      sku: string
      quantity: number
    }>
  }
  actions: ReportActionLink[]
}

export type ReportSummaryResponse<TSummary> = {
  data: TSummary
  meta: {
    generated_at: string
  }
}

export type OrdersReportSummaryResponse = ReportSummaryResponse<OrdersReportSummary>
export type InventoryReportSummaryResponse = ReportSummaryResponse<InventoryReportSummary>
export type ReturnsReportSummaryResponse = ReportSummaryResponse<ReturnsReportSummary>

export type ReportComparisonMetricViewModel = {
  id: string
  label: string
  currentValue: string
  previousValue: string
  deltaValue: string
  direction: 'up' | 'down' | 'flat'
  deltaPercentageLabel: string | null
}

export type ReportTableCell = {
  value: string
  tone?: 'default' | 'muted' | 'mono' | 'badge'
  badgeVariant?: 'default' | 'secondary' | 'outline' | 'destructive'
}

export type ReportTableColumn = {
  key: string
  label: string
  align?: 'left' | 'right'
}

export type ReportTableSection = {
  id: string
  title: string
  description: string
  columns: ReportTableColumn[]
  rows: Array<Record<string, ReportTableCell>>
  emptyMessage: string
}

export type ReportInsightCard = {
  id: string
  label: string
  value: string
  description: string
}
