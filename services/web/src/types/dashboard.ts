export type DashboardRange = {
  from: string | null
  to: string | null
  is_all_time: boolean
}

export type CountMap = Record<string, number>

export type OrdersSummary = {
  range: DashboardRange
  total_orders: number
  total_revenue: string
  avg_order_value: string
  by_status: CountMap
}

export type InventorySummary = {
  range: DashboardRange
  total_skus: number
  total_on_hand: number
  total_reserved: number
  total_available: number
  low_stock_count: number
  movement_in_qty: number
  movement_out_qty: number
}

export type ReturnsSummary = {
  range: DashboardRange
  total_returns: number
  total_return_items_qty: number
  restockable_items_qty: number
  non_restockable_items_qty: number
  by_order_status: CountMap
}

export type DashboardSummary = {
  range: DashboardRange
  orders?: OrdersSummary
  inventory?: InventorySummary
  returns?: ReturnsSummary
}

export type DashboardSummaryResponse = {
  data: DashboardSummary
  meta: {
    generated_at: string
  }
}

type SummaryResponse<T> = {
  data: T
  meta: {
    generated_at: string
  }
}

export type OrdersSummaryResponse = SummaryResponse<OrdersSummary>
export type InventorySummaryResponse = SummaryResponse<InventorySummary>
export type ReturnsSummaryResponse = SummaryResponse<ReturnsSummary>
