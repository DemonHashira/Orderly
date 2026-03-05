export type DashboardKpiBlockId =
  | 'orders-total'
  | 'orders-revenue'
  | 'inventory-low-stock'
  | 'returns-total'

export type DashboardChartBlockId = 'orders-by-status' | 'returns-by-outcome' | 'inventory-flow'

export type DashboardQueueBlockId =
  | 'ready-to-ship'
  | 'shipment-follow-up'
  | 'returns-to-restock'
  | 'inventory-attention'

export type DashboardBlockId = DashboardKpiBlockId | DashboardChartBlockId | DashboardQueueBlockId

export type DashboardVariant = 'owner' | 'order_manager' | 'logistics' | 'inventory' | 'generic'

export type DashboardVisibility = {
  kpis: DashboardKpiBlockId[]
  charts: DashboardChartBlockId[]
  queues: DashboardQueueBlockId[]
}

export type DashboardLayoutPlan = {
  kpis: DashboardKpiBlockId[]
  charts: DashboardChartBlockId[]
  queues: DashboardQueueBlockId[]
}

export type DashboardRoleView = 'owner' | 'order_manager' | 'logistics' | 'inventory' | 'generic'

export type ResolveDashboardRoleViewInput = {
  permissions: string[]
  roles: string[]
}

export type ResolveDashboardVariantInput = {
  permissions: string[]
  roles?: string[]
}

export type ResolveDashboardVisibilityInput = {
  permissions: string[]
  hasOrdersSummary: boolean
  hasInventorySummary: boolean
  hasReturnsSummary: boolean
}
