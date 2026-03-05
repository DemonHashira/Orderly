import type {
  DashboardChartBlockId,
  DashboardKpiBlockId,
  DashboardQueueBlockId,
  ResolveDashboardVisibilityInput,
  DashboardVisibility,
} from './types'

const hasPermission = (permissionSet: Set<string>, permission: string) =>
  permissionSet.has(permission)

export const resolveDashboardVisibility = ({
  permissions,
  hasOrdersSummary,
  hasInventorySummary,
  hasReturnsSummary,
}: ResolveDashboardVisibilityInput): DashboardVisibility => {
  const permissionSet = new Set(permissions)

  const kpis: DashboardKpiBlockId[] = []
  const charts: DashboardChartBlockId[] = []
  const queues: DashboardQueueBlockId[] = []

  if (hasPermission(permissionSet, 'reports.orders.view') && hasOrdersSummary) {
    kpis.push('orders-total', 'orders-revenue')
    charts.push('orders-by-status')
  }

  if (hasPermission(permissionSet, 'reports.inventory.view') && hasInventorySummary) {
    kpis.push('inventory-low-stock')
    charts.push('inventory-flow')
  }

  if (hasPermission(permissionSet, 'reports.returns.view') && hasReturnsSummary) {
    kpis.push('returns-total')
    charts.push('returns-by-outcome')
  }

  if (hasPermission(permissionSet, 'orders.view')) {
    queues.push('ready-to-ship')
  }

  if (hasPermission(permissionSet, 'shipments.view')) {
    queues.push('shipment-follow-up')
  }

  if (
    hasPermission(permissionSet, 'returns.view') &&
    (hasPermission(permissionSet, 'returns.restock') ||
      hasPermission(permissionSet, 'inventory.return_restock.approve'))
  ) {
    queues.push('returns-to-restock')
  }

  if (hasPermission(permissionSet, 'inventory.view')) {
    queues.push('inventory-attention')
  }

  return { kpis, charts, queues }
}
